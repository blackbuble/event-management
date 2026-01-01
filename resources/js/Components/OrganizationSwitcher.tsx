import React, { useState } from 'react';
import { usePage, router, Link } from '@inertiajs/react';
import { ChevronsUpDown, Check, Plus, Building2 } from 'lucide-react';

export default function OrganizationSwitcher() {
    const { auth } = usePage().props as any;
    const { organizations, current_organization } = auth.user;
    const [isOpen, setIsOpen] = useState(false);

    const switchOrg = (orgId: number) => {
        router.post(route('organizations.switch', orgId), {}, {
            preserveState: false, // Force reload to update all props
        });
        setIsOpen(false);
    };

    if (!current_organization) return null;

    return (
        <div className="relative px-3 mb-6">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="w-full flex items-center justify-between p-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-colors group"
            >
                <div className="flex items-center space-x-2 overflow-hidden">
                    <div className="h-6 w-6 rounded bg-indigo-600 flex items-center justify-center shrink-0 shadow-sm group-hover:shadow-md transition-shadow">
                        {current_organization.logo ? (
                            <img src={current_organization.logo} alt="" className="h-full w-full object-cover rounded" />
                        ) : (
                            <span className="text-white text-xs font-bold uppercase">{current_organization.name?.[0] || 'O'}</span>
                        )}
                    </div>
                    <span className="text-sm font-medium text-slate-700 truncate group-hover:text-slate-900 transition-colors">{current_organization.name}</span>
                </div>
                <ChevronsUpDown size={16} className="text-slate-400 shrink-0" />
            </button>

            {isOpen && (
                <>
                    <div className="fixed inset-0 z-10" onClick={() => setIsOpen(false)} />
                    <div className="absolute left-3 right-3 top-full mt-1 bg-white border border-slate-100 shadow-xl rounded-lg z-20 py-1 max-h-64 overflow-y-auto">
                        <div className="px-3 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            Organizations
                        </div>
                        {organizations.map((org: any) => (
                            <button
                                key={org.id}
                                onClick={() => switchOrg(org.id)}
                                className="w-full text-left px-3 py-2 flex items-center space-x-2 hover:bg-slate-50 transition-colors"
                            >
                                <div className={`h-5 w-5 rounded flex items-center justify-center shrink-0 ${current_organization.id === org.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500'}`}>
                                    <span className="text-xs font-bold uppercase">{org.name[0]}</span>
                                </div>
                                <span className={`text-sm flex-1 truncate ${current_organization.id === org.id ? 'font-semibold text-slate-900' : 'text-slate-600'}`}>
                                    {org.name}
                                </span>
                                {current_organization.id === org.id && <Check size={14} className="text-indigo-600" />}
                            </button>
                        ))}
                        <div className="border-t border-slate-50 mt-1 pt-1">
                            <Link
                                href={route('organizations.create')}
                                className="w-full text-left px-3 py-2 flex items-center space-x-2 hover:bg-slate-50 text-indigo-600 transition-colors"
                                onClick={() => setIsOpen(false)}
                            >
                                <Plus size={16} />
                                <span className="text-sm font-medium">Create Organization</span>
                            </Link>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
