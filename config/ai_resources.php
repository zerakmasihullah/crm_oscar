<?php

use App\Models\FieldReport;
use App\Models\User;
use App\Models\Role;

/**
 * Agent-operable resources for General Chat.
 *
 * Add one entry when you introduce a new section (kit:scaffold appends this).
 * Generic CRUD tools read this catalog — you do not write per-module tools.
 *
 * @return array<string, array<string, mixed>>
 */
return [

    'field_reports' => [
        'label' => 'Field reports',
        'model' => FieldReport::class,
        'permission' => 'field_reports',
        'index_route' => null,
        'edit_route' => null,
        'user_scoped' => true,
        'user_id_column' => 'user_id',
        'search' => ['title', 'location', 'body'],
        'default_order' => ['created_at', 'desc'],
        'display' => ['id', 'title', 'status', 'location'],
        'title_attribute' => 'title',
        'fillable' => ['title', 'body', 'location', 'status'],
        'create_extras' => ['client_uuid', 'user_id', 'client_updated_at'],
        'rules' => [
            'create' => [
                'title' => ['required', 'string', 'max:255'],
                'body' => ['nullable', 'string', 'max:10000'],
                'location' => ['nullable', 'string', 'max:255'],
                'status' => ['nullable', 'string', 'in:draft,submitted'],
            ],
            'update' => [
                'title' => ['sometimes', 'string', 'max:255'],
                'body' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'location' => ['sometimes', 'nullable', 'string', 'max:255'],
                'status' => ['sometimes', 'string', 'in:draft,submitted'],
            ],
        ],
        'sensitive' => [],
        'require_approval' => ['delete'],
        'privilege_fields' => [],
        'protected_names' => [],
    ],

    'users' => [
        'label' => 'Users',
        'model' => User::class,
        'handler' => 'users',
        'permission' => 'users',
        'index_route' => 'settings.users.index',
        'edit_route' => null,
        'user_scoped' => false,
        'search' => ['name', 'email'],
        'default_order' => ['name', 'asc'],
        'display' => ['id', 'name', 'email', 'is_active'],
        'title_attribute' => 'name',
        'fillable' => ['name', 'email', 'password', 'is_active'],
        'rules' => [
            'create' => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'max:255'],
                'roles' => ['nullable', 'array'],
                'roles.*' => ['string', 'exists:roles,name'],
            ],
            'update' => [
                'name' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'email', 'max:255'],
                'password' => ['sometimes', 'string', 'min:8', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
                'roles' => ['sometimes', 'array'],
                'roles.*' => ['string', 'exists:roles,name'],
            ],
        ],
        'sensitive' => [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ],
        'require_approval' => ['create', 'update', 'delete'],
        'privilege_fields' => ['roles'],
        'protected_names' => [],
        'related_tables' => ['model_has_roles'],
    ],

    'roles' => [
        'label' => 'Roles',
        'model' => Role::class,
        'handler' => 'roles',
        'permission' => 'roles',
        'index_route' => 'settings.roles.index',
        'edit_route' => null,
        'user_scoped' => false,
        'search' => ['name'],
        'default_order' => ['name', 'asc'],
        'display' => ['id', 'name'],
        'title_attribute' => 'name',
        'fillable' => ['name'],
        'rules' => [
            'create' => [
                'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ],
            'update' => [
                'name' => ['sometimes', 'string', 'max:255'],
                'permissions' => ['sometimes', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ],
        ],
        'sensitive' => [],
        'require_approval' => ['create', 'update', 'delete'],
        'privilege_fields' => ['permissions'],
        'protected_names' => ['Owner'],
        'related_tables' => ['model_has_roles', 'role_has_permissions', 'permissions'],
    ],

    'field_notes' => [
        'label' => 'Field notes',
        'model' => \App\Models\FieldNote::class,
        'permission' => 'field_notes',
        'index_route' => 'field-notes.index',
        'edit_route' => 'field-notes.edit',
        'user_scoped' => true,
        'search' => ['name'],
        'default_order' => ['id', 'desc'],
        'display' => ['id', 'name'],
        'title_attribute' => 'name',
        'fillable' => ['name'],
        'create_extras' => ['client_uuid', 'client_updated_at', 'user_id'],
        'rules' => [
            'create' => [
                'name' => ['required', 'string', 'max:255'],
            ],
            'update' => [
                'name' => ['sometimes', 'string', 'max:255'],
            ],
        ],
        'sensitive' => [],
        'require_approval' => ['delete'],
        'privilege_fields' => [],
        'protected_names' => [],
    ],
];
