export type LocaleCode = string;

export type TextDirection = 'ltr' | 'rtl';

export type LocaleOption = {
    code: LocaleCode;
    name: string;
    native: string;
    dir: TextDirection;
};
