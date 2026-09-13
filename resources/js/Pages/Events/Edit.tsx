import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EventForm from '@/Components/EventForm';
import { eventTexts, EventLanguage, defaultEventLanguage } from '@/config/event-texts';
import { AlertCircle } from 'lucide-react';

interface EventEditProps {
    id: number;
    title: string;
    description: string;
    type: 'online' | 'offline' | 'hybrid';
    venue_name: string;
    venue_address: string;
    meeting_link: string | null;
    latitude: number | null;
    longitude: number | null;
    start_date: string;
    end_date: string;
    capacity: number | null;
    status: string;
    image_url: string | null;
}

export default function EditEvent() {
    const { locale, flash, event } = usePage().props as any;
    const currentLang = (locale as EventLanguage) || defaultEventLanguage;
    const t = eventTexts[currentLang];
    const eventRow = event as EventEditProps;

    return (
        <DashboardLayout>
            <Head title={t.edit.title} />

            <div className="max-w-3xl mx-auto space-y-8">
                {flash?.error && (
                    <div className="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm font-medium">
                        <AlertCircle size={18} />
                        <span>{flash.error}</span>
                    </div>
                )}

                <EventForm
                    initial={{
                        title: eventRow.title,
                        description: eventRow.description,
                        type: eventRow.type,
                        venue_name: eventRow.venue_name,
                        venue_address: eventRow.venue_address,
                        meeting_link: eventRow.meeting_link ?? '',
                        latitude: eventRow.latitude !== null ? String(eventRow.latitude) : '',
                        longitude: eventRow.longitude !== null ? String(eventRow.longitude) : '',
                        start_date: eventRow.start_date,
                        end_date: eventRow.end_date,
                        capacity: eventRow.capacity !== null ? String(eventRow.capacity) : '',
                        status: eventRow.status === 'published' ? 'published' : 'draft',
                    }}
                    existingImageUrl={eventRow.image_url}
                    submitUrl={route('events.update', eventRow.id)}
                    method="patch"
                    title={t.edit.title}
                    subtitle={t.edit.subtitle}
                />
            </div>
        </DashboardLayout>
    );
}
