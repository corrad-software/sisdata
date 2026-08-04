'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Dropdown, DropdownLink } from '@/components/ui/Dropdown';

export default function OverlaysPage() {
    const [infoOpen, setInfoOpen] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <div className="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Modal</h3>

                <div className="flex flex-wrap gap-3">
                    <Button variant="primary" onClick={() => setInfoOpen(true)}>
                        Open info modal
                    </Button>
                    <Button variant="danger" onClick={() => setConfirmOpen(true)}>
                        Open confirm modal
                    </Button>
                </div>

                <Modal open={infoOpen} onClose={() => setInfoOpen(false)}>
                    <div className="p-6">
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Just a modal</h2>
                        <p className="mt-1 text-body-sm text-txt-black-500">
                            This is the shared Modal component — opened and closed via local component state.
                        </p>
                        <div className="mt-6 flex justify-end">
                            <Button variant="secondary" onClick={() => setInfoOpen(false)}>
                                Close
                            </Button>
                        </div>
                    </div>
                </Modal>

                <Modal open={confirmOpen} onClose={() => setConfirmOpen(false)}>
                    <div className="p-6">
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Are you sure?</h2>
                        <p className="mt-1 text-body-sm text-txt-black-500">
                            This is the same confirmation pattern used by the User Management delete flow.
                        </p>
                        <div className="mt-6 flex justify-end gap-3">
                            <Button variant="secondary" onClick={() => setConfirmOpen(false)}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={() => setConfirmOpen(false)}>
                                Confirm
                            </Button>
                        </div>
                    </div>
                </Modal>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Dropdown</h3>

                <Dropdown align="left" width="w-48" trigger={<Button variant="secondary">Open dropdown</Button>}>
                    <DropdownLink href="#">Item one</DropdownLink>
                    <DropdownLink href="#">Item two</DropdownLink>
                    <DropdownLink href="#">Item three</DropdownLink>
                </Dropdown>
            </section>
        </div>
    );
}
