export function Stepper({ steps, current = 1 }: { steps: string[]; current?: number }) {
    return (
        <ol className="flex items-start w-full">
            {steps.map((label, index) => {
                const stepNumber = index + 1;
                const isLast = index === steps.length - 1;

                return (
                    <li key={label} className={`flex items-center ${!isLast ? 'flex-1' : ''}`}>
                        <div className="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2">
                            <span
                                className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-body-sm font-medium ${
                                    stepNumber < current
                                        ? 'bg-primary-600 text-white'
                                        : stepNumber === current
                                          ? 'border-2 border-primary-600 text-primary-600'
                                          : 'border-2 border-otl-gray-300 text-txt-black-500'
                                }`}
                            >
                                {stepNumber < current ? (
                                    <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fillRule="evenodd"
                                            clipRule="evenodd"
                                            d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                        />
                                    </svg>
                                ) : (
                                    stepNumber
                                )}
                            </span>

                            <span
                                className={`text-body-xs text-center sm:text-body-sm sm:text-left font-medium ${
                                    stepNumber <= current ? 'text-txt-black-900' : 'text-txt-black-500'
                                }`}
                            >
                                {label}
                            </span>
                        </div>

                        {!isLast && (
                            <div
                                className={`mx-3 flex-1 ${
                                    stepNumber < current ? 'h-1 rounded-full bg-primary-600' : 'border-t-2 border-dashed border-otl-gray-300'
                                }`}
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
