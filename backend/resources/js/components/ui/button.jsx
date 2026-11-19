import { cva } from 'class-variance-authority';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

const variants = cva(
    'inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'bg-emerald-800 text-white hover:bg-emerald-900',
                outline: 'border border-emerald-900/20 bg-white text-emerald-950 hover:bg-emerald-50',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

export function Button({ className, variant, type = 'button', ...props }) {
    return <button type={type} className={twMerge(clsx(variants({ variant: variant ?? 'default' }), className))} {...props} />;
}
