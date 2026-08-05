'use client';

import { useState } from 'react';
import { PageContainer } from '@/components/PageContainer';
import { FieldInput } from '@/components/ui/FieldInput';
import { InputLabel } from '@/components/ui/InputLabel';
import { QuickSearch } from '@/components/ui/QuickSearch';
import { Textarea } from '@/components/ui/Textarea';
import { Calendar } from '@/components/ui/Calendar';
import { Radio } from '@/components/ui/Radio';
import { Toggle } from '@/components/ui/Toggle';
import { FileUpload } from '@/components/ui/FileUpload';
import { Button } from '@/components/ui/Button';

function PasswordToggleDemo() {
    const [show, setShow] = useState(false);
    const [value, setValue] = useState('');

    return (
        <div className="max-w-sm">
            <InputLabel htmlFor="ks_password_toggle" className="mb-1.5">
                Password
            </InputLabel>
            <div className="relative">
                <input
                    type={show ? 'text' : 'password'}
                    id="ks_password_toggle"
                    name="ks_password_toggle"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    placeholder="Enter your password"
                    className="block w-full rounded-md border border-otl-gray-300 bg-bg-white pe-10 text-body-sm text-txt-black-900 shadow-sm focus:border-primary-600 focus:ring-1 focus:ring-fr-primary"
                />
                <button
                    type="button"
                    onClick={() => setShow((s) => !s)}
                    className="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-txt-black-500 hover:text-txt-black-900"
                    aria-label={show ? 'Hide password' : 'Show password'}
                >
                    {!show ? (
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                            />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    ) : (
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                            />
                        </svg>
                    )}
                </button>
            </div>
        </div>
    );
}

export default function FormsPage() {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [bioCounted, setBioCounted] = useState('');
    const [dateField, setDateField] = useState('');
    const [bio, setBio] = useState('');
    const [radioRole, setRadioRole] = useState('admin');
    const [toggleOn, setToggleOn] = useState(true);
    const [toggleOff, setToggleOff] = useState(false);

    const [formName, setFormName] = useState('');
    const [formEmail, setFormEmail] = useState('');
    const [formBio, setFormBio] = useState('');
    const [formRole, setFormRole] = useState('user');
    const [formActive, setFormActive] = useState(true);

    return (
        <PageContainer className="space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Form Input</h3>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                    <FieldInput label="Name" name="ks_name" value={name} onChange={setName} placeholder="Jane Doe" />
                    <FieldInput
                        label="Email"
                        type="email"
                        name="ks_email"
                        value={email}
                        onChange={setEmail}
                        required
                        placeholder="jane@example.com"
                    />
                    <FieldInput
                        label="Password"
                        type="password"
                        name="ks_password"
                        value={password}
                        onChange={setPassword}
                        help="Must be at least 8 characters."
                    />
                    <FieldInput
                        label="Email (with error)"
                        name="ks_email_error"
                        value="not-an-email"
                        onChange={() => {}}
                        error="Please enter a valid email address."
                    />
                    <FieldInput label="Disabled" name="ks_disabled" value="Can't touch this" onChange={() => {}} />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Password Input</h3>
                <p className="text-body-sm text-txt-black-500">
                    A show/hide toggle built on top of the plain input — no changes needed to the shared FieldInput
                    component.
                </p>

                <PasswordToggleDemo />
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Search Bar</h3>
                <p className="text-body-sm text-txt-black-500">
                    The navbar quick-search dropdown, plus a standalone inline variant for use inside a page (e.g.
                    filtering a table).
                </p>

                <div className="space-y-4">
                    <div>
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">Navbar variant</p>
                        <QuickSearch />
                    </div>

                    <div className="max-w-sm">
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">Inline variant</p>
                        <div className="relative">
                            <svg
                                className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-txt-black-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                strokeWidth={2}
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                                />
                            </svg>
                            <input
                                type="search"
                                placeholder="Search..."
                                className="block w-full rounded-md border border-otl-gray-300 bg-bg-white py-2 ps-9 text-body-sm text-txt-black-900 shadow-sm focus:border-primary-600 focus:ring-1 focus:ring-fr-primary"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Character Count</h3>
                <p className="text-body-sm text-txt-black-500">
                    An optional maxLength prop on Textarea shows a live counter as you type.
                </p>

                <div className="max-w-2xl">
                    <Textarea
                        label="Short bio"
                        name="ks_bio_counted"
                        rows={3}
                        maxLength={120}
                        value={bioCounted}
                        onChange={setBioCounted}
                        placeholder="Tell us a little about yourself..."
                    />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Date Field</h3>
                <p className="text-body-sm text-txt-black-500">
                    A plain formatted-text date field — distinct from the popup Calendar, useful when the exact
                    display format matters more than a picker UI.
                </p>

                <div className="max-w-xs">
                    <FieldInput
                        label="Date of Birth"
                        name="ks_date_field"
                        value={dateField}
                        onChange={setDateField}
                        placeholder="DD/MM/YYYY"
                        help="Format: DD/MM/YYYY"
                    />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Select & Checkbox</h3>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                    <div>
                        <InputLabel htmlFor="ks_role" className="mb-1.5">
                            Role
                        </InputLabel>
                        <select
                            id="ks_role"
                            className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                        >
                            <option>User</option>
                            <option>Admin</option>
                        </select>
                    </div>

                    <label className="flex items-center gap-2 mt-7">
                        <input
                            type="checkbox"
                            defaultChecked
                            className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                        />
                        <span className="text-body-sm text-txt-black-900">Active</span>
                    </label>

                    <div>
                        <InputLabel htmlFor="ks_permissions" className="mb-1.5">
                            Permissions
                        </InputLabel>
                        <select
                            id="ks_permissions"
                            multiple
                            size={4}
                            defaultValue={['View Users', 'Edit Users']}
                            className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                        >
                            <option>View Users</option>
                            <option>Edit Users</option>
                            <option>Delete Users</option>
                            <option>Manage Roles</option>
                            <option>View Reports</option>
                        </select>
                        <p className="mt-1 text-body-xs text-txt-black-500">
                            Hold Cmd/Ctrl (or Shift for a range) to select more than one.
                        </p>
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Date Picker</h3>
                <p className="text-body-sm text-txt-black-500">
                    Native browser date inputs — the calendar UI is provided by the OS/browser, no JS library needed.
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                    <FieldInput label="Date" type="date" name="ks_date" value="" onChange={() => {}} />
                    <FieldInput label="Date/Time" type="datetime-local" name="ks_datetime" value="" onChange={() => {}} />
                    <FieldInput label="Start Date" type="date" name="ks_date_start" value="" onChange={() => {}} />
                    <FieldInput label="End Date" type="date" name="ks_date_end" value="" onChange={() => {}} />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Calendar</h3>
                <p className="text-body-sm text-txt-black-500">
                    Click the month or year label to drill down; click again to return to the day view.
                </p>

                <Calendar selected="2024-08-14" max="2024-09-30" />
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Duration Picker</h3>
                <p className="text-body-sm text-txt-black-500">
                    Same calendar, in range mode — click a start date, then an end date. Dates in between highlight
                    automatically.
                </p>

                <Calendar range />
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Textarea</h3>

                <div className="max-w-2xl">
                    <Textarea
                        label="Bio"
                        name="ks_bio"
                        value={bio}
                        onChange={setBio}
                        placeholder="Tell us a little about yourself..."
                        help="Max 500 characters."
                    />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Radio Group</h3>

                <div>
                    <InputLabel className="mb-1.5">Role</InputLabel>
                    <div className="flex flex-wrap gap-4">
                        <Radio
                            name="ks_role_radio"
                            value="admin"
                            label="Admin"
                            checked={radioRole === 'admin'}
                            onChange={() => setRadioRole('admin')}
                        />
                        <Radio
                            name="ks_role_radio"
                            value="user"
                            label="User"
                            checked={radioRole === 'user'}
                            onChange={() => setRadioRole('user')}
                        />
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Toggle</h3>

                <div className="flex flex-wrap gap-6">
                    <Toggle name="ks_toggle_on" label="Email notifications" checked={toggleOn} onChange={setToggleOn} />
                    <Toggle name="ks_toggle_off" label="SMS notifications" checked={toggleOff} onChange={setToggleOff} />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">File Upload</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Matches the MYDS file upload pattern exactly — try selecting a file over 1MB on the first
                        example to see the Error state, or any file to see the brief Loading state.
                    </p>
                </div>

                <div className="space-y-4 max-w-2xl">
                    <div>
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">Default</p>
                        <FileUpload title="Upload Required Documents" name="ks_file" formats="JPG, PNG & PDF" maxSizeMb={1} />
                    </div>

                    <div>
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">Disabled</p>
                        <FileUpload
                            title="Upload Required Documents"
                            name="ks_file_disabled"
                            formats="JPG, PNG & PDF"
                            maxSizeMb={25}
                            disabled
                        />
                    </div>

                    <div>
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">
                            Uploaded, with previously uploaded files
                        </p>
                        <FileUpload
                            title="Upload Required Documents"
                            name="ks_file_previous"
                            formats="JPG, PNG & PDF"
                            maxSizeMb={25}
                            previousFiles={[
                                { name: 'Document.pdf', size: '1.2 MB' },
                                { name: 'Document.pdf', size: '1.2 MB', disabled: true },
                            ]}
                        />
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Complete Form Example</h3>
                <p className="text-body-sm text-txt-black-500">
                    The same components composed into a realistic form, matching the pattern used by the User
                    Management CRUD.
                </p>

                <form className="max-w-2xl space-y-4" onSubmit={(e) => e.preventDefault()}>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FieldInput
                            label="Name"
                            name="ks_form_name"
                            value={formName}
                            onChange={setFormName}
                            required
                            placeholder="Jane Doe"
                        />
                        <FieldInput
                            label="Email"
                            type="email"
                            name="ks_form_email"
                            value={formEmail}
                            onChange={setFormEmail}
                            required
                            placeholder="jane@example.com"
                        />
                    </div>

                    <Textarea
                        label="Bio"
                        name="ks_form_bio"
                        rows={3}
                        value={formBio}
                        onChange={setFormBio}
                        placeholder="Tell us a little about yourself..."
                    />

                    <div>
                        <InputLabel className="mb-1.5">Role</InputLabel>
                        <div className="flex flex-wrap gap-4">
                            <Radio
                                name="ks_form_role"
                                value="admin"
                                label="Admin"
                                checked={formRole === 'admin'}
                                onChange={() => setFormRole('admin')}
                            />
                            <Radio
                                name="ks_form_role"
                                value="user"
                                label="User"
                                checked={formRole === 'user'}
                                onChange={() => setFormRole('user')}
                            />
                        </div>
                    </div>

                    <Toggle name="ks_form_active" label="Active" checked={formActive} onChange={setFormActive} />

                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="secondary" type="reset">
                            Cancel
                        </Button>
                        <Button variant="primary" type="submit">
                            Save
                        </Button>
                    </div>
                </form>
            </section>
        </PageContainer>
    );
}
