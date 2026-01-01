import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Users, Mail, Shield, MoreHorizontal, Plus } from 'lucide-react';
import InviteMemberModal from '@/Components/InviteMemberModal';

export default function Show({ organization, members }: any) {
    const [isInviteModalOpen, setIsInviteModalOpen] = useState(false);

    return (
        <DashboardLayout>
            <Head title={`${organization.name} - Team`} />

            <InviteMemberModal
                show={isInviteModalOpen}
                onClose={() => setIsInviteModalOpen(false)}
                organizationId={organization.id}
            />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">{organization.name}</h1>
                        <p className="text-slate-500">Manage your team members and permissions.</p>
                    </div>
                    <button
                        onClick={() => setIsInviteModalOpen(true)}
                        className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm shadow-indigo-500/30"
                    >
                        <Plus size={18} className="mr-2" />
                        Invite Member
                    </button>
                </div>

                {/* Members List */}
                <div className="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <div className="p-6 border-b border-slate-50 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-slate-900 flex items-center">
                            <Users size={20} className="mr-2 text-slate-400" />
                            Team Members
                            <span className="ml-2 bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full text-xs font-bold">
                                {members.total}
                            </span>
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50 text-xs uppercase text-slate-500 font-semibold">
                                <tr>
                                    <th className="px-6 py-4">User</th>
                                    <th className="px-6 py-4">Role</th>
                                    <th className="px-6 py-4">Joined</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {members.data.map((member: any) => (
                                    <tr key={member.id} className="hover:bg-slate-50/50 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center">
                                                <div className="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-indigo-600 font-bold mr-3 border-2 border-white shadow-sm scheme-auth-avatar">
                                                    {member.avatar ? (
                                                        <img src={member.avatar} alt="" className="h-full w-full rounded-full object-cover" />
                                                    ) : (
                                                        member.name[0]
                                                    )}
                                                </div>
                                                <div>
                                                    <div className="font-medium text-slate-900">{member.name}</div>
                                                    <div className="text-sm text-slate-500">{member.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            {member.roles.map((role: any) => (
                                                <span key={role.id} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-600 border border-indigo-100 bg-opacity-50">
                                                    <Shield size={12} className="mr-1" />
                                                    {role.name}
                                                </span>
                                            ))}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-500">
                                            {new Date(member.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <button className="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-100 rounded-lg transition-colors">
                                                <MoreHorizontal size={18} />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination if needed */}
                </div>
            </div>
        </DashboardLayout>
    );
}
