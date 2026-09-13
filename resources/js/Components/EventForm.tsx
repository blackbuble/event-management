import React, { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Input, Textarea, Button } from '@/Components/Form';
import { eventTexts, EventLanguage, defaultEventLanguage } from '@/config/event-texts';
import {
    MapPin,
    Video,
    Globe2,
    Image as ImageIcon,
    Upload,
    X,
    Save,
    Rocket,
    ArrowLeft,
} from 'lucide-react';

export type EventType = 'offline' | 'online' | 'hybrid';

export interface EventFormValues {
    title: string;
    description: string;
    type: EventType;
    venue_name: string;
    venue_address: string;
    meeting_link: string;
    latitude: string;
    longitude: string;
    start_date: string;
    end_date: string;
    capacity: string;
    status: 'draft' | 'published';
    image: File | null;
}

interface EventFormProps {
    initial: Partial<EventFormValues>;
    existingImageUrl?: string | null;
    submitUrl: string;
    method: 'post' | 'patch';
    title: string;
    subtitle: string;
}

const typeCards: Array<{
    value: EventType;
    icon: typeof MapPin;
    titleKey: 'type_offline' | 'type_online' | 'type_hybrid';
    descKey: 'type_offline_desc' | 'type_online_desc' | 'type_hybrid_desc';
}> = [
    { value: 'offline', icon: MapPin, titleKey: 'type_offline', descKey: 'type_offline_desc' },
    { value: 'online', icon: Video, titleKey: 'type_online', descKey: 'type_online_desc' },
    { value: 'hybrid', icon: Globe2, titleKey: 'type_hybrid', descKey: 'type_hybrid_desc' },
];

export default function EventForm({ initial, existingImageUrl, submitUrl, method, title, subtitle }: EventFormProps) {
    const { locale } = usePage().props as any;
    const currentLang = (locale as EventLanguage) || defaultEventLanguage;
    const t = eventTexts[currentLang].create;

    const { data, setData, post, patch, processing, errors } = useForm<EventFormValues>({
        title: initial.title ?? '',
        description: initial.description ?? '',
        type: initial.type ?? 'offline',
        venue_name: initial.venue_name ?? '',
        venue_address: initial.venue_address ?? '',
        meeting_link: initial.meeting_link ?? '',
        latitude: initial.latitude ?? '',
        longitude: initial.longitude ?? '',
        start_date: initial.start_date ?? '',
        end_date: initial.end_date ?? '',
        capacity: initial.capacity ?? '',
        status: initial.status ?? 'draft',
        image: null,
    });

    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [showLocation, setShowLocation] = useState(Boolean(initial.latitude || initial.longitude));
    const [linkLater, setLinkLater] = useState(false);

    const needsVenue = data.type === 'offline' || data.type === 'hybrid';
    const needsMeetingLink = data.type === 'online' || data.type === 'hybrid';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { forceFormData: true };
        if (method === 'patch') {
            patch(submitUrl, options);
        } else {
            post(submitUrl, options);
        }
    };

    const handleImage = (file: File | null) => {
        setData('image', file);
        if (imagePreview) URL.revokeObjectURL(imagePreview);
        setImagePreview(file ? URL.createObjectURL(file) : null);
    };

    const minDateTime = new Date(Date.now() + 60 * 60 * 1000).toISOString().slice(0, 16);

    return (
        <div className="max-w-3xl mx-auto space-y-8">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Link
                    href={route('events.index')}
                    className="p-2.5 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-slate-900 hover:border-slate-300 transition-all"
                >
                    <ArrowLeft size={18} />
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">{title}</h1>
                    <p className="text-slate-500 text-sm mt-0.5">{subtitle}</p>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-8">
                {/* Event Type */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.type_label}</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {typeCards.map(({ value, icon: Icon, titleKey, descKey }) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => {
                                    setData('type', value);
                                    if (value === 'offline') {
                                        setData('meeting_link', '');
                                        setLinkLater(false);
                                    }
                                }}
                                className={`p-4 rounded-xl border-2 text-left transition-all duration-200 ${
                                    data.type === value
                                        ? 'border-indigo-600 bg-indigo-50/50 shadow-sm'
                                        : 'border-slate-200 bg-white hover:border-slate-300'
                                }`}
                            >
                                <Icon
                                    size={22}
                                    className={data.type === value ? 'text-indigo-600' : 'text-slate-400'}
                                />
                                <p className={`mt-2 font-semibold text-sm ${data.type === value ? 'text-indigo-700' : 'text-slate-900'}`}>
                                    {t[titleKey]}
                                </p>
                                <p className="text-xs text-slate-500 mt-1 leading-relaxed">{t[descKey]}</p>
                            </button>
                        ))}
                    </div>
                    {errors.type && (
                        <p className="text-xs font-medium text-red-500">{errors.type}</p>
                    )}
                </section>

                {/* Basics */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <Input
                        label={t.title_label}
                        placeholder={t.title_placeholder}
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        error={errors.title}
                        maxLength={255}
                        required
                    />
                    <Textarea
                        label={t.description_label}
                        placeholder={t.description_placeholder}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        error={errors.description}
                        maxLength={5000}
                        rows={5}
                        required
                    />

                    {/* Banner */}
                    <div className="space-y-1.5">
                        <label className="text-sm font-medium text-slate-700 ml-1">{t.image_label}</label>
                        {imagePreview ? (
                            <div className="relative rounded-xl overflow-hidden border border-slate-200">
                                <img src={imagePreview} alt="preview" className="w-full h-40 object-cover" />
                                <button
                                    type="button"
                                    onClick={() => handleImage(null)}
                                    className="absolute top-2 right-2 p-1.5 rounded-lg bg-slate-900/70 text-white hover:bg-slate-900 transition-colors"
                                >
                                    <X size={14} />
                                </button>
                            </div>
                        ) : existingImageUrl ? (
                            <div className="relative rounded-xl overflow-hidden border border-slate-200">
                                <img src={existingImageUrl} alt="banner" className="w-full h-40 object-cover" />
                                <div className="absolute bottom-2 left-2 bg-slate-900/70 text-white text-xs px-2 py-1 rounded-md">
                                    {t.image_current}
                                </div>
                            </div>
                        ) : (
                            <label className="flex flex-col items-center justify-center gap-2 h-32 rounded-xl border-2 border-dashed border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/30 transition-all cursor-pointer">
                                <div className="flex items-center gap-2 text-slate-400">
                                    <Upload size={20} />
                                    <ImageIcon size={20} />
                                </div>
                                <span className="text-xs text-slate-400">{t.image_hint}</span>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/jpg,image/webp"
                                    className="hidden"
                                    onChange={(e) => handleImage(e.target.files?.[0] ?? null)}
                                />
                            </label>
                        )}
                        {imagePreview === null && existingImageUrl && (
                            <label className="text-xs text-indigo-600 hover:text-indigo-700 cursor-pointer font-medium ml-1 inline-block">
                                {t.image_replace}
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/jpg,image/webp"
                                    className="hidden"
                                    onChange={(e) => handleImage(e.target.files?.[0] ?? null)}
                                />
                            </label>
                        )}
                        {errors.image && (
                            <p className="text-xs font-medium text-red-500 ml-1">{errors.image}</p>
                        )}
                    </div>
                </section>

                {/* Venue / Meeting Link */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    {needsVenue && (
                        <>
                            <Input
                                label={t.venue_name_label}
                                placeholder={t.venue_name_placeholder}
                                value={data.venue_name}
                                onChange={(e) => setData('venue_name', e.target.value)}
                                error={errors.venue_name}
                                maxLength={255}
                                required
                            />
                            <Textarea
                                label={t.venue_address_label}
                                placeholder={t.venue_address_placeholder}
                                value={data.venue_address}
                                onChange={(e) => setData('venue_address', e.target.value)}
                                error={errors.venue_address}
                                maxLength={1000}
                                rows={2}
                                required
                            />
                        </>
                    )}
                    {needsMeetingLink && (
                        <div className="space-y-3">
                            <div className="flex gap-2">
                                {[false, true].map((isLater) => (
                                    <button
                                        key={String(isLater)}
                                        type="button"
                                        onClick={() => {
                                            setLinkLater(isLater);
                                            if (isLater) setData('meeting_link', '');
                                        }}
                                        className={`px-4 py-2 rounded-lg text-sm font-medium border transition-all ${
                                            linkLater === isLater
                                                ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                                                : 'border-slate-200 text-slate-500 hover:border-slate-300'
                                        }`}
                                    >
                                        {isLater ? t.link_later : t.link_now}
                                    </button>
                                ))}
                            </div>
                            {linkLater ? (
                                <p className="text-xs text-slate-400 ml-1 leading-relaxed">{t.link_later_hint}</p>
                            ) : (
                                <Input
                                    label={t.meeting_link_label}
                                    placeholder={t.meeting_link_placeholder}
                                    value={data.meeting_link}
                                    onChange={(e) => setData('meeting_link', e.target.value)}
                                    error={errors.meeting_link}
                                    type="url"
                                    maxLength={255}
                                    required={!linkLater}
                                />
                            )}
                        </div>
                    )}

                    {/* Optional location pin */}
                    {needsVenue && (
                        <div className="space-y-3">
                            <button
                                type="button"
                                onClick={() => setShowLocation(!showLocation)}
                                className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                {t.location_section}
                            </button>
                            {showLocation && (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <Input
                                        label={t.latitude_label}
                                        placeholder={t.latitude_placeholder}
                                        value={data.latitude}
                                        onChange={(e) => setData('latitude', e.target.value)}
                                        error={errors.latitude}
                                        type="number"
                                        step="any"
                                    />
                                    <Input
                                        label={t.longitude_label}
                                        placeholder={t.longitude_placeholder}
                                        value={data.longitude}
                                        onChange={(e) => setData('longitude', e.target.value)}
                                        error={errors.longitude}
                                        type="number"
                                        step="any"
                                    />
                                    <p className="text-xs text-slate-400 sm:col-span-2 -mt-2 ml-1">{t.location_hint}</p>
                                </div>
                            )}
                        </div>
                    )}
                </section>

                {/* Schedule & Capacity */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label={t.start_date_label}
                            type="datetime-local"
                            value={data.start_date}
                            onChange={(e) => setData('start_date', e.target.value)}
                            error={errors.start_date}
                            min={method === 'post' ? minDateTime : undefined}
                            required
                        />
                        <Input
                            label={t.end_date_label}
                            type="datetime-local"
                            value={data.end_date}
                            onChange={(e) => setData('end_date', e.target.value)}
                            error={errors.end_date}
                            min={data.start_date || minDateTime}
                            required
                        />
                    </div>
                    <Input
                        label={t.capacity_label}
                        placeholder={t.capacity_placeholder}
                        value={data.capacity}
                        onChange={(e) => setData('capacity', e.target.value)}
                        error={errors.capacity}
                        type="number"
                        min={1}
                    />
                </section>

                {/* Status */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide">{t.status_label}</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {(['draft', 'published'] as const).map((status) => {
                            const isActive = data.status === status;
                            const isDraft = status === 'draft';
                            return (
                                <button
                                    key={status}
                                    type="button"
                                    onClick={() => setData('status', status)}
                                    className={`flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-all duration-200 ${
                                        isActive
                                            ? isDraft
                                                ? 'border-slate-500 bg-slate-50'
                                                : 'border-emerald-600 bg-emerald-50/50'
                                            : 'border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    {isDraft ? (
                                        <Save size={20} className={isActive ? 'text-slate-600' : 'text-slate-400'} />
                                    ) : (
                                        <Rocket size={20} className={isActive ? 'text-emerald-600' : 'text-slate-400'} />
                                    )}
                                    <span>
                                        <span className={`block font-semibold text-sm ${isActive ? (isDraft ? 'text-slate-800' : 'text-emerald-700') : 'text-slate-900'}`}>
                                            {isDraft ? t.status_draft : t.status_published}
                                        </span>
                                        <span className="block text-xs text-slate-500 mt-0.5">
                                            {isDraft ? t.status_draft_desc : t.status_published_desc}
                                        </span>
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {errors.status && (
                        <p className="text-xs font-medium text-red-500">{errors.status}</p>
                    )}
                </section>

                {/* Actions */}
                <div className="flex items-center justify-end gap-3 pb-8">
                    <Link
                        href={route('events.index')}
                        className="px-5 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors"
                    >
                        {t.cancel}
                    </Link>
                    <Button type="submit" isLoading={processing}>
                        {processing ? t.submitting : t.submit}
                    </Button>
                </div>
            </form>
        </div>
    );
}
