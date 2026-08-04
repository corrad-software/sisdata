'use client';

import { useRef, useState } from 'react';
import { Spinner } from './Spinner';

interface PreviousFile {
    name: string;
    size: string;
    disabled?: boolean;
}

interface FileUploadProps {
    title?: string;
    formats?: string;
    maxSizeMb?: number;
    name?: string;
    multiple?: boolean;
    disabled?: boolean;
    previousFiles?: PreviousFile[];
}

function formatSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function extensionOf(name: string) {
    const parts = name.split('.');
    return parts.length > 1 ? (parts.pop() ?? '').toUpperCase() : '';
}

export function FileUpload({
    title,
    formats = 'JPG, PNG & PDF',
    maxSizeMb = 25,
    name,
    multiple = true,
    disabled = false,
    previousFiles = [],
}: FileUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function addFiles(fileList: FileList) {
        setError(null);
        const incoming = Array.from(fileList);
        const accepted: File[] = [];

        for (const file of incoming) {
            if (file.size > maxSizeMb * 1024 * 1024) {
                setError(`Please upload file smaller than ${maxSizeMb}MB.`);
                continue;
            }
            accepted.push(file);
        }

        if (!accepted.length) return;

        setLoading(true);
        setTimeout(() => {
            setFiles((prev) => (multiple ? [...prev, ...accepted] : accepted.slice(-1)));
            setLoading(false);
        }, 600);
    }

    function removeFile(index: number) {
        setFiles((prev) => prev.filter((_, i) => i !== index));
    }

    return (
        <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    {title && <h4 className="text-body-sm font-semibold text-txt-black-900">{title}</h4>}
                    <p className="text-body-xs text-txt-black-500">File types: {formats}</p>
                    <p className="text-body-xs text-txt-black-500">Max file size: {maxSizeMb}MB</p>
                </div>

                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    disabled={disabled}
                    className="inline-flex flex-shrink-0 items-center gap-1.5 rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"
                        />
                    </svg>
                    Upload
                </button>

                <input
                    ref={inputRef}
                    type="file"
                    id={name}
                    name={name}
                    multiple={multiple}
                    disabled={disabled}
                    onChange={(e) => e.target.files && addFiles(e.target.files)}
                    className="hidden"
                />
            </div>

            {error && <p className="mt-3 text-body-xs text-danger-600">{error}</p>}

            {loading && (
                <div className="mt-3 flex items-center gap-2.5 rounded-md border border-otl-gray-200 px-3 py-2.5">
                    <Spinner size="sm" className="flex-shrink-0 text-txt-black-500" />
                    <span className="text-body-sm text-txt-black-500">Uploading...</span>
                </div>
            )}

            {files.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-3 border-t border-otl-gray-200 pt-3">
                    {files.map((file, index) => {
                        const ext = extensionOf(file.name);
                        const badgeClass =
                            ext === 'PDF' ? 'bg-danger-600' : ['JPG', 'JPEG', 'PNG'].includes(ext) ? 'bg-primary-600' : 'bg-bg-black-500';

                        return (
                            <div
                                key={`${file.name}-${file.size}-${index}`}
                                className="flex items-center gap-2.5 rounded-md border border-otl-gray-200 px-3 py-2"
                            >
                                <span
                                    className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded text-[9px] font-bold text-white ${badgeClass}`}
                                >
                                    {ext}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-body-sm font-medium text-txt-black-900">{file.name}</p>
                                    <p className="text-body-xs text-txt-black-500">{formatSize(file.size)}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => removeFile(index)}
                                    className="ms-1 flex-shrink-0 text-danger-600 hover:text-danger-700"
                                    aria-label="Remove file"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}

            {previousFiles.length > 0 && (
                <div className="mt-3 border-t border-otl-gray-200 pt-3">
                    <p className="mb-2 text-body-xs text-txt-black-500">Previously uploaded</p>
                    <div className="flex flex-wrap gap-3">
                        {previousFiles.map((file, index) => (
                            <div
                                key={index}
                                className={`flex items-center gap-2.5 rounded-md border border-otl-gray-200 px-3 py-2 ${file.disabled ? 'opacity-50' : ''}`}
                            >
                                <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded bg-danger-600 text-[9px] font-bold text-white">
                                    {extensionOf(file.name)}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-body-sm font-medium text-txt-black-900">{file.name}</p>
                                    <p className="text-body-xs text-txt-black-500">{file.size}</p>
                                </div>
                                {!file.disabled && (
                                    <button
                                        type="button"
                                        className="ms-1 flex-shrink-0 text-danger-600 hover:text-danger-700"
                                        aria-label="Remove file"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
