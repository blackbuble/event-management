import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import { Mail, Shield, UserPlus } from 'lucide-react';

interface InviteMemberModalProps {
    show: boolean;
    onClose: () => void;
    organizationId: number;
}

export default function InviteMemberModal({ show, onClose, organizationId }: InviteMemberModalProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'member', // Default role
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('organizations.members.invite', organizationId), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="text-center mb-6">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 mb-4">
                    <UserPlus className="h-6 w-6 text-indigo-600" aria-hidden="true" />
                </div>
                <h3 className="text-lg font-semibold leading-6 text-slate-900">Invite Team Member</h3>
                <p className="mt-2 text-sm text-slate-500">
                    Invite a new member to join this organization. They will receive an email invitation.
                </p>
            </div>

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-slate-700">
                            Email Address
                        </label>
                        <div className="mt-1 relative rounded-md shadow-sm">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Mail className="h-5 w-5 text-slate-400" aria-hidden="true" />
                            </div>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                className={`block w-full rounded-lg border-slate-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm ${errors.email ? 'border-red-300' : ''}`}
                                placeholder="colleague@example.com"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        </div>
                        {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                    </div>

                    <div>
                        <label htmlFor="role" className="block text-sm font-medium text-slate-700">
                            Role
                        </label>
                        <div className="mt-1 relative rounded-md shadow-sm">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Shield className="h-5 w-5 text-slate-400" aria-hidden="true" />
                            </div>
                            <select
                                id="role"
                                name="role"
                                className="block w-full rounded-lg border-slate-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                value={data.role}
                                onChange={(e) => setData('role', e.target.value)}
                            >
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            Admins have full access to organization settings.
                        </p>
                    </div>
                </div>

                <div className="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        className="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        onClick={onClose}
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex justify-center rounded-lg border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {processing ? 'Sending Invite...' : 'Send Invitation'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
