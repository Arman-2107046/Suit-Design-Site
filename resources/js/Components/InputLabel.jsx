export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                `block text-[13px] font-medium tracking-wide text-gray-700 ` +
                className
            }
        >
            {value ? value : children}
        </label>
    );
}
