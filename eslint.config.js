import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import prettier from 'eslint-config-prettier';

export default tseslint.config(
    { ignores: ['public/build/**', 'vendor/**', 'node_modules/**', 'storage/**'] },
    js.configs.recommended,
    ...tseslint.configs.recommendedTypeChecked,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            globals: { ...globals.browser },
            parserOptions: {
                project: './tsconfig.json',
                tsconfigRootDir: import.meta.dirname,
            },
        },
        plugins: { react, 'react-hooks': reactHooks },
        settings: { react: { version: '18.3' } },
        rules: {
            ...react.configs['jsx-runtime'].rules,
            ...reactHooks.configs.recommended.rules,
            '@typescript-eslint/consistent-type-imports': ['error', { fixStyle: 'inline-type-imports' }],
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],

            // Admin code must never be imported from another surface — that is
            // what keeps the admin bundle out of the advertiser build.
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['**/admin/**'],
                            message: 'Admin code is a separate bundle. Move shared code to resources/js/shared.',
                        },
                    ],
                },
            ],
        },
    },
    {
        // The admin surface may of course import its own files.
        files: ['resources/js/admin/**/*.{ts,tsx}'],
        rules: { 'no-restricted-imports': 'off' },
    },
    {
        // QuantBar is the catalog's signature treatment and means nothing
        // outside it. This makes "catalog only" enforceable rather than a
        // comment someone can miss.
        files: ['resources/js/**/*.tsx'],
        rules: {
            'no-restricted-syntax': [
                'error',
                {
                    selector: 'JSXOpeningElement[name.name="QuantBar"]',
                    message:
                        'QuantBar is used only in the catalog. Use a plain number, or StatCard for a headline metric.',
                },
            ],
        },
    },
    {
        // The catalog itself, the component's own definition, and the gallery
        // that documents it.
        files: [
            'resources/js/advertiser/Pages/Catalog/**/*.tsx',
            // The catalog's own components. The page was one file when this
            // rule was written; the rule means "the catalog", and the catalog
            // is now a page plus the pieces it is built from.
            'resources/js/advertiser/Components/catalog/**/*.tsx',
            'resources/js/shared/ui/QuantBar.tsx',
            'resources/js/advertiser/Components/design-system/**/*.tsx',
        ],
        rules: { 'no-restricted-syntax': 'off' },
    },
    {
        files: ['*.config.{js,ts}', 'vite.config.ts', 'tailwind.config.ts'],
        languageOptions: { globals: { ...globals.node } },
    },
    prettier,
);
