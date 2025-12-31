import React from 'react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Utility for merging tailwind classes
 */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    containerClassName?: string;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({ label, error, className, containerClassName, type = 'text', ...props }, ref) => {
        return (
            <div className={cn("space-y-1.5 w-full", containerClassName)}>
                {label && (
                    <label className="text-sm font-medium text-slate-700 dark:text-slate-300 ml-1">
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    type={type}
                    className={cn(
                        "w-full px-4 py-2.5 rounded-xl border transition-all duration-200 outline-none",
                        "bg-white dark:bg-slate-900",
                        "border-slate-200 dark:border-slate-800",
                        "focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10",
                        "placeholder:text-slate-400 text-slate-900 dark:text-white text-base md:text-sm",
                        error && "border-red-500 focus:border-red-500 focus:ring-red-500/10",
                        className
                    )}
                    {...props}
                />
                {error && (
                    <p className="text-xs font-medium text-red-500 ml-1 animate-in fade-in slide-in-from-top-1">
                        {error}
                    </p>
                )}
            </div>
        );
    }
);

Input.displayName = 'Input';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    isLoading?: boolean;
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
    size?: 'sm' | 'md' | 'lg';
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ isLoading, variant = 'primary', size = 'md', className, children, disabled, ...props }, ref) => {
        const variants = {
            primary: "bg-indigo-600 text-white hover:bg-indigo-700 shadow-md shadow-indigo-500/20 active:scale-[0.98]",
            secondary: "bg-slate-100 text-slate-900 hover:bg-slate-200 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700 active:scale-[0.98]",
            ghost: "bg-transparent text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-900",
            danger: "bg-red-500 text-white hover:bg-red-600 shadow-md shadow-red-500/20 active:scale-[0.98]",
        };

        const sizes = {
            sm: "px-3 py-1.5 text-xs",
            md: "px-5 py-2.5 text-sm",
            lg: "px-6 py-3 text-base font-semibold",
        };

        return (
            <button
                ref={ref}
                disabled={isLoading || disabled}
                className={cn(
                    "relative flex items-center justify-center rounded-xl font-medium transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed",
                    variants[variant],
                    sizes[size],
                    className
                )}
                {...props}
            >
                {isLoading && (
                    <div className="absolute inset-0 flex items-center justify-center bg-inherit rounded-inherit">
                        <svg className="animate-spin h-5 w-5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                )}
                <span className={cn(isLoading && "invisible")}>{children}</span>
            </button>
        );
    }
);

Button.displayName = 'Button';
