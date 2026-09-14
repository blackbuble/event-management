import React from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EventForm from '@/Components/EventForm';
import { eventTexts, EventLanguage, defaultEventLanguage } from '@/config/event-texts';
import { usePage } from '@inertiajs/react';

export default function CreateEvent() {
    const { locale, categories, cities } = usePage().props as any;
    const currentLang = (locale as EventLanguage) || defaultEventLanguage;
    const t = eventTexts[currentLang].create;

    return (
        <DashboardLayout>
            <Head title={t.title} />
            <EventForm
                initial={{}}
                categories={categories ?? []}
                cities={cities ?? []}
                submitUrl={route('events.store')}
                method="post"
                title={t.title}
                subtitle={t.subtitle}
            />
        </DashboardLayout>
    );
}
