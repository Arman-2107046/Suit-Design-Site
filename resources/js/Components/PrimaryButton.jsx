export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-full bg-gray-900 px-6 py-3 text-sm font-medium text-white transition-all duration-200 hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/40 focus-visible:ring-offset-2 active:scale-[0.98] ${
                    disabled ? 'cursor-not-allowed opacity-50' : ''
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
