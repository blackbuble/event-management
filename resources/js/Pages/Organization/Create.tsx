import React from 'react';
import { useForm, Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Building2 } from 'lucide-react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('organizations.store'));
    };

    const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const name = e.target.value;
        const slug = name.toLowerCase()
            .replace(/ /g, '-')
            .replace(/[^\w-]+/g, '');

        setData(data => ({
            ...data,
            name: name,
            slug: slug
        }));
    };

    return (
        <DashboardLayout>
            <Head title="Create Organization" />

            <div className="max-w-2xl mx-auto py-8">
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center p-3 bg-indigo-100 rounded-full mb-4">
                        <Building2 size={32} className="text-indigo-600" />
                    </div>
                    <h1 className="text-3xl font-bold text-slate-900">Create New Organization</h1>
                    <p className="mt-2 text-slate-500">Set up a new workspace for your team and events.</p>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-slate-100 p-8">
                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Organization Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={handleNameChange}
                                className={`w-full rounded-lg border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 transition-colors ${errors.name ? 'border-red-300 focus:border-red-500 focus:ring-red-200' : ''}`}
                                placeholder="e.g. Acme Events Inc."
                                autoFocus
                            />
                            {errors.name && <div className="text-red-600 text-sm mt-1">{errors.name}</div>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Workspace URL</label>
                            <div className="flex rounded-lg shadow-sm">
                                <span className="inline-flex items-center px-4 rounded-l-lg border border-r-0 border-slate-200 bg-slate-50 text-slate-500 text-sm">
                                    app.eventhub.com/
                                </span>
                                <input
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    className={`flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-lg border-slate-200 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${errors.slug ? 'border-red-300 focus:border-red-500' : ''}`}
                                    placeholder="acme-events"
                                />
                            </div>
                            <p className="mt-1 text-xs text-slate-500">This will be the unique URL for your organization.</p>
                            {errors.slug && <div className="text-red-600 text-sm mt-1">{errors.slug}</div>}
                        </div>

                        <div className="pt-4 flex items-center justify-end space-x-3">
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="px-4 py-2 text-slate-600 hover:text-slate-900 font-medium transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium shadow-sm shadow-indigo-500/30 transition-all transform active:scale-95"
                            >
                                {processing ? 'Creating Workspace...' : 'Create Workspace'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </DashboardLayout>
    );
}
