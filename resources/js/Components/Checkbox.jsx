export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'h-4 w-4 rounded border-gray-300 text-gray-900 shadow-none focus:ring-gray-900/30 ' +
                className
            }
        />
    );
}
