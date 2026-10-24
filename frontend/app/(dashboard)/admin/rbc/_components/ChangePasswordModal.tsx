'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { rbc } from '@/lib/rbc';

/** SRS-RBC-12: change own password; `forced` = cannot be dismissed (expired / first login). */
export function ChangePasswordModal({ open, forced, onClose, onChanged }: { open: boolean; forced: boolean; onClose: () => void; onChanged: () => void }) {
    const [current, setCurrent] = useState('');
    const [next, setNext] = useState('');
    const [confirm, setConfirm] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [saving, setSaving] = useState(false);

    async function submit(e: FormEvent) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            await rbc.changePassword({ kata_laluan_semasa: current, kata_laluan: next, kata_laluan_confirmation: confirm });
            toast.success('Kata laluan berjaya ditukar.');
            setCurrent('');
            setNext('');
            setConfirm('');
            onChanged();
        } catch (error) {
            if (error instanceof ApiError && error.errors) setErrors(error.errors);
            else toast.error(error instanceof Error ? error.message : 'Kata laluan tidak dapat ditukar.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal open={open} onClose={forced ? () => undefined : onClose}>
            <form onSubmit={submit} className="p-6 space-y-4" aria-labelledby="tukar-kata-laluan">
                <h2 id="tukar-kata-laluan" className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                    Tukar Kata Laluan
                </h2>
                {forced && (
                    <Alert type="warning">
                        Kata laluan anda telah tamat tempoh atau ditetapkan oleh pentadbir. Sila tukar kata laluan sebelum meneruskan.
                    </Alert>
                )}
                <p className="text-body-sm text-txt-black-500">
                    Sekurang-kurangnya 12 aksara dengan tiga daripada empat jenis: huruf kecil, huruf besar, nombor dan simbol. Lima kata laluan terakhir tidak boleh digunakan semula.
                </p>
                <FieldInput label="Kata laluan semasa" name="kata_laluan_semasa" type="password" value={current} onChange={setCurrent} required autoComplete="current-password" error={errors.kata_laluan_semasa?.[0]} />
                <FieldInput label="Kata laluan baharu" name="kata_laluan" type="password" value={next} onChange={setNext} required autoComplete="new-password" error={errors.kata_laluan?.join(' ')} />
                <FieldInput label="Sahkan kata laluan baharu" name="kata_laluan_confirmation" type="password" value={confirm} onChange={setConfirm} required autoComplete="new-password" />
                <div className="flex justify-end gap-3 pt-2">
                    {!forced && (
                        <Button variant="secondary" onClick={onClose}>
                            Batal
                        </Button>
                    )}
                    <Button type="submit" disabled={saving}>
                        Tukar Kata Laluan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
