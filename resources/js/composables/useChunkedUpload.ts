import { sha256 } from '@noble/hashes/sha2.js';
import { bytesToHex } from '@noble/hashes/utils.js';

export type UploadSessionPayload = {
    id: string;
    status: string;
    original_filename: string;
    safe_filename: string;
    total_bytes: number;
    chunk_bytes: number;
    total_chunks: number;
    received_chunks: number[];
    missing_chunks: number[];
    received_bytes: number;
    progress_percent: number;
    checksum_algo: string;
    expected_checksum: string | null;
    actual_checksum: string | null;
    destination: string;
    destination_root: string | null;
    destination_path: string | null;
    final_path: string | null;
    detected_mime: string | null;
    error_message: string | null;
    attachment_id?: number | null;
    download_url?: string | null;
    replaced_attachment_id?: number | null;
    expires_at: string | null;
    completed_at: string | null;
};

export type ChunkedUploadConfig = {
    max_bytes: number;
    chunk_bytes: number;
    min_chunk_bytes: number;
    max_chunk_bytes: number;
    concurrency: number;
    require_chunk_checksum: boolean;
    require_file_checksum: boolean;
    checksum_algo: string;
    session_ttl_hours: number;
    simple_max_bytes: number;
};

export type ChunkedUploadItem = {
    id: string;
    file: File;
    status: 'queued' | 'hashing' | 'uploading' | 'assembling' | 'completed' | 'failed' | 'aborted' | 'paused';
    progress: number;
    loadedBytes: number;
    totalBytes: number;
    speedBps: number;
    error: string | null;
    sessionId: string | null;
    finalPath: string | null;
    attachmentId: number | null;
    downloadUrl: string | null;
};

export type ChunkedUploadOptions = {
    destination?: 'file_manager' | 'attachment' | 'disk';
    root?: string;
    path?: string;
    disk?: string;
    directory?: string;
    replacePath?: string | null;
    attachable?: string;
    attachableId?: number | string;
    replaceAttachmentId?: number | null;
    concurrency?: number;
    chunkBytes?: number;
    computeFileChecksum?: boolean;
    onItemUpdate?: (item: ChunkedUploadItem) => void;
    signal?: AbortSignal;
};

type InitiateResponse = {
    message?: string;
    session: UploadSessionPayload;
};

type SessionResponse = {
    message?: string;
    session: UploadSessionPayload;
};

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function jsonHeaders(): Record<string, string> {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };
}

function formHeaders(): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };
}

async function readError(response: Response): Promise<string> {
    try {
        const data = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (data.message) {
            return data.message;
        }

        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;

        if (first) {
            return first;
        }
    } catch {
        // fall through
    }

    return `Upload failed (HTTP ${response.status})`;
}

async function sha256Hex(data: ArrayBuffer): Promise<string> {
    return bytesToHex(sha256(new Uint8Array(data)));
}

async function sha256File(file: File, signal?: AbortSignal): Promise<string> {
    const hasher = sha256.create();
    const chunkSize = 4 * 1024 * 1024;
    let offset = 0;

    while (offset < file.size) {
        if (signal?.aborted) {
            throw new DOMException('Aborted', 'AbortError');
        }

        const slice = file.slice(offset, Math.min(offset + chunkSize, file.size));
        hasher.update(new Uint8Array(await slice.arrayBuffer()));
        offset += chunkSize;
    }

    return bytesToHex(hasher.digest());
}

async function mapPool<T>(
    items: T[],
    concurrency: number,
    worker: (item: T, index: number) => Promise<void>,
    shouldStop?: () => boolean,
): Promise<void> {
    let next = 0;
    const runners = Array.from({ length: Math.max(1, concurrency) }, async () => {
        while (true) {
            if (shouldStop?.()) {
                return;
            }

            const current = next++;

            if (current >= items.length) {
                return;
            }

            await worker(items[current], current);
        }
    });

    await Promise.all(runners);
}

export async function fetchChunkedUploadConfig(signal?: AbortSignal): Promise<ChunkedUploadConfig> {
    const response = await fetch('/settings/uploads/config', {
        headers: jsonHeaders(),
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        throw new Error(await readError(response));
    }

    return (await response.json()) as ChunkedUploadConfig;
}

export function createUploadItem(file: File): ChunkedUploadItem {
    return {
        id: crypto.randomUUID(),
        file,
        status: 'queued',
        progress: 0,
        loadedBytes: 0,
        totalBytes: file.size,
        speedBps: 0,
        error: null,
        sessionId: null,
        finalPath: null,
        attachmentId: null,
        downloadUrl: null,
    };
}

export async function uploadFileChunked(
    item: ChunkedUploadItem,
    options: ChunkedUploadOptions,
): Promise<ChunkedUploadItem> {
    const notify = (patch: Partial<ChunkedUploadItem>): ChunkedUploadItem => {
        Object.assign(item, patch);
        options.onItemUpdate?.({ ...item });

        return item;
    };

    const signal = options.signal;
    const throwIfAborted = (): void => {
        if (signal?.aborted) {
            throw new DOMException('Aborted', 'AbortError');
        }
    };

    try {
        if (item.file.size <= 0) {
            throw new Error('File is empty.');
        }

        notify({ status: 'hashing', error: null, progress: 0, loadedBytes: 0 });

        let fileChecksum: string | undefined;

        if (options.computeFileChecksum) {
            fileChecksum = await sha256File(item.file, signal);
        }

        throwIfAborted();

        const initiateResponse = await fetch('/settings/uploads', {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            signal,
            body: JSON.stringify({
                filename: item.file.name,
                size: item.file.size,
                mime: item.file.type || null,
                checksum: fileChecksum ?? null,
                chunk_bytes: options.chunkBytes ?? null,
                destination: options.destination ?? 'file_manager',
                root: options.root ?? null,
                path: options.path ?? '',
                disk: options.disk ?? null,
                directory: options.directory ?? null,
                replace_path: options.replacePath ?? null,
                attachable: options.attachable ?? null,
                attachable_id: options.attachableId ?? null,
                replace_attachment_id: options.replaceAttachmentId ?? null,
            }),
        });

        if (!initiateResponse.ok) {
            throw new Error(await readError(initiateResponse));
        }

        const initiated = (await initiateResponse.json()) as InitiateResponse;
        const session = initiated.session;
        const chunkBytes = session.chunk_bytes;
        const concurrency = Math.max(1, options.concurrency ?? 3);
        const startedAt = performance.now();

        notify({
            status: 'uploading',
            sessionId: session.id,
            progress: session.progress_percent,
            loadedBytes: session.received_bytes,
        });

        const indexes =
            session.missing_chunks.length > 0
                ? session.missing_chunks
                : Array.from({ length: session.total_chunks }, (_, i) => i);

        let loaded = session.received_bytes;
        const doneChunks = new Set(session.received_chunks);

        await mapPool(
            indexes,
            concurrency,
            async (index) => {
                throwIfAborted();

                const start = index * chunkBytes;
                const end = Math.min(start + chunkBytes, item.file.size);
                const blob = item.file.slice(start, end);
                const buffer = await blob.arrayBuffer();
                const checksum = await sha256Hex(buffer);

                const maxAttempts = 4;
                let attempt = 0;
                let lastError: Error | null = null;

                while (attempt < maxAttempts) {
                    attempt++;
                    throwIfAborted();

                    try {
                        const body = new FormData();
                        body.append('index', String(index));
                        body.append('checksum', checksum);
                        body.append('chunk', new Blob([buffer]), `chunk-${index}.part`);

                        const response = await fetch(`/settings/uploads/${session.id}/chunks`, {
                            method: 'POST',
                            headers: formHeaders(),
                            credentials: 'same-origin',
                            signal,
                            body,
                        });

                        if (!response.ok) {
                            throw new Error(await readError(response));
                        }

                        if (!doneChunks.has(index)) {
                            doneChunks.add(index);
                            loaded = Math.min(item.file.size, loaded + (end - start));
                        }

                        const elapsed = Math.max(0.001, (performance.now() - startedAt) / 1000);
                        notify({
                            status: 'uploading',
                            loadedBytes: loaded,
                            progress: Math.min(99.9, (loaded / item.file.size) * 100),
                            speedBps: loaded / elapsed,
                        });

                        return;
                    } catch (error) {
                        lastError = error instanceof Error ? error : new Error(String(error));

                        if (signal?.aborted || attempt >= maxAttempts) {
                            throw lastError;
                        }

                        await new Promise((resolve) => setTimeout(resolve, 400 * 2 ** (attempt - 1)));
                    }
                }

                throw lastError ?? new Error('Chunk upload failed.');
            },
            () => Boolean(signal?.aborted),
        );

        throwIfAborted();
        notify({ status: 'assembling', progress: 99.9 });

        const completeResponse = await fetch(`/settings/uploads/${session.id}/complete`, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            signal,
            body: '{}',
        });

        if (!completeResponse.ok) {
            throw new Error(await readError(completeResponse));
        }

        const completedPayload = (await completeResponse.json()) as SessionResponse;

        return notify({
            status: 'completed',
            progress: 100,
            loadedBytes: item.file.size,
            finalPath: completedPayload.session.final_path,
            attachmentId: completedPayload.session.attachment_id ?? null,
            downloadUrl: completedPayload.session.download_url ?? null,
            error: null,
        });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            if (item.sessionId) {
                try {
                    await fetch(`/settings/uploads/${item.sessionId}`, {
                        method: 'DELETE',
                        headers: jsonHeaders(),
                        credentials: 'same-origin',
                    });
                } catch {
                    // ignore abort cleanup failures
                }
            }

            return notify({ status: 'aborted', error: 'Upload cancelled.' });
        }

        return notify({
            status: 'failed',
            error: error instanceof Error ? error.message : String(error),
        });
    }
}

export async function uploadFilesChunked(
    files: File[],
    options: ChunkedUploadOptions,
): Promise<ChunkedUploadItem[]> {
    const items = files.map(createUploadItem);
    const results: ChunkedUploadItem[] = [];

    for (const item of items) {
        options.onItemUpdate?.(item);
        results.push(await uploadFileChunked(item, options));
    }

    return results;
}
