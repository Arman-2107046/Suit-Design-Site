import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'rounded-xl border-gray-200 bg-white px-4 py-3 text-[15px] text-gray-900 placeholder-gray-400 shadow-none transition-colors duration-200 focus:border-gray-900 focus:ring-0 ' +
                className
            }
            ref={localRef}
        />
    );
});
