import React, { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Input, Textarea, Button, Select } from '@/Components/Form';
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
    Plus,
    Trash2,
    Ticket as TicketIcon,
    ChevronDown,
} from 'lucide-react';

export type EventType = 'offline' | 'online' | 'hybrid';

export interface TicketFormValues {
    id?: number;
    name: string;
    description: string;
    price: string;
    quantity: string;
    sale_starts: string;
    sale_ends: string;
    min_per_order: string;
    max_per_order: string;
    is_active: boolean;
}

export interface EventFormValues {
    title: string;
    description: string;
    type: EventType;
    category: string;
    venue_name: string;
    venue_address: string;
    meeting_link: string;
    latitude: string;
    longitude: string;
    start_date: string;
    end_date: string;
    capacity: string;
    status: 'draft' | 'published';
    whatsapp_enabled: boolean;
    image: File | null;
    tickets: TicketFormValues[];
}

export type RawTicket = Partial<TicketFormValues> & {
    price?: string | number;
    quantity?: string | number;
    min_per_order?: string | number;
    max_per_order?: string | number;
};

const emptyTicket = (): TicketFormValues => ({
    name: '',
    description: '',
    price: '0',
    quantity: '',
    sale_starts: '',
    sale_ends: '',
    min_per_order: '1',
    max_per_order: '10',
    is_active: true,
});

const normalizeTickets = (tickets?: RawTicket[]): TicketFormValues[] => {
    if (!tickets || tickets.length === 0) {
        return [emptyTicket()];
    }

    return tickets.map((ticket) => ({
        ...emptyTicket(),
        ...ticket,
        id: ticket.id,
        price: ticket.price !== undefined && ticket.price !== null ? String(ticket.price) : '0',
        quantity: ticket.quantity !== undefined && ticket.quantity !== null ? String(ticket.quantity) : '',
        min_per_order:
            ticket.min_per_order !== undefined && ticket.min_per_order !== null
                ? String(ticket.min_per_order)
                : '1',
        max_per_order:
            ticket.max_per_order !== undefined && ticket.max_per_order !== null
                ? String(ticket.max_per_order)
                : '10',
        is_active: ticket.is_active ?? true,
    }));
};

interface EventFormProps {
    initial: Partial<Omit<EventFormValues, 'tickets'>> & { tickets?: RawTicket[] };
    categories: Array<{ value: string; label: string }>;
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

export default function EventForm({ initial, categories, existingImageUrl, submitUrl, method, title, subtitle }: EventFormProps) {
    const { locale } = usePage().props as any;
    const currentLang = (locale as EventLanguage) || defaultEventLanguage;
    const t = eventTexts[currentLang].create;

    const { data, setData, post, patch, processing, errors } = useForm<EventFormValues>({
        title: initial.title ?? '',
        description: initial.description ?? '',
        type: initial.type ?? 'offline',
        category: initial.category ?? categories[0]?.value ?? 'other',
        venue_name: initial.venue_name ?? '',
        venue_address: initial.venue_address ?? '',
        meeting_link: initial.meeting_link ?? '',
        latitude: initial.latitude ?? '',
        longitude: initial.longitude ?? '',
        start_date: initial.start_date ?? '',
        end_date: initial.end_date ?? '',
        capacity: initial.capacity ?? '',
        status: initial.status ?? 'draft',
        whatsapp_enabled: initial.whatsapp_enabled ?? false,
        image: null,
        tickets: normalizeTickets(initial.tickets),
    });

    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [showLocation, setShowLocation] = useState(Boolean(initial.latitude || initial.longitude));
    const [linkLater, setLinkLater] = useState(false);
    const [openTicketWindows, setOpenTicketWindows] = useState<number[]>([]);

    const updateTicket = <K extends keyof TicketFormValues>(index: number, key: K, value: TicketFormValues[K]) => {
        setData(
            'tickets',
            data.tickets.map((ticket, i) => (i === index ? { ...ticket, [key]: value } : ticket))
        );
    };

    const addTicket = () => setData('tickets', [...data.tickets, emptyTicket()]);

    const removeTicket = (index: number) => {
        if (data.tickets.length <= 1) return;
        setData('tickets', data.tickets.filter((_, i) => i !== index));
        setOpenTicketWindows((open) => open.filter((i) => i !== index).map((i) => (i > index ? i - 1 : i)));
    };

    const toggleTicketWindow = (index: number) => {
        setOpenTicketWindows((open) => (open.includes(index) ? open.filter((i) => i !== index) : [...open, index]));
    };

    const fieldError = (index: number, key: string): string | undefined =>
        (errors as unknown as Record<string, string>)[`tickets.${index}.${key}`];

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

                    <Select
                        label={t.category_label}
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                        error={errors.category}
                        options={categories}
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

                {/* Tickets */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide flex items-center gap-2">
                                <TicketIcon size={16} className="text-indigo-600" />
                                {t.tickets_label}
                            </h2>
                            <p className="text-xs text-slate-500 mt-1">{t.tickets_hint}</p>
                        </div>
                        <button
                            type="button"
                            onClick={addTicket}
                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-indigo-600 border border-indigo-200 hover:bg-indigo-50 transition-colors shrink-0"
                        >
                            <Plus size={16} />
                            {t.ticket_add}
                        </button>
                    </div>

                    <div className="space-y-4">
                        {data.tickets.map((ticket, index) => {
                            const windowOpen = openTicketWindows.includes(index);
                            return (
                                <div key={index} className="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                            {t.ticket_number.replace('{n}', String(index + 1))}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => removeTicket(index)}
                                            disabled={data.tickets.length <= 1}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                        >
                                            <Trash2 size={14} />
                                            {t.ticket_remove}
                                        </button>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Input
                                            label={t.ticket_name_label}
                                            placeholder={t.ticket_name_placeholder}
                                            value={ticket.name}
                                            onChange={(e) => updateTicket(index, 'name', e.target.value)}
                                            error={fieldError(index, 'name')}
                                            maxLength={255}
                                            required
                                        />
                                        <Input
                                            label={t.ticket_quantity_label}
                                            placeholder={t.ticket_quantity_placeholder}
                                            value={ticket.quantity}
                                            onChange={(e) => updateTicket(index, 'quantity', e.target.value)}
                                            error={fieldError(index, 'quantity')}
                                            type="number"
                                            min={1}
                                            required
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <Input
                                            label={t.ticket_price_label}
                                            placeholder={t.ticket_price_placeholder}
                                            value={ticket.price}
                                            onChange={(e) => updateTicket(index, 'price', e.target.value)}
                                            error={fieldError(index, 'price')}
                                            type="number"
                                            min={0}
                                            step="any"
                                            hint={t.ticket_free_hint}
                                            required
                                        />
                                        <Input
                                            label={t.ticket_min_label}
                                            value={ticket.min_per_order}
                                            onChange={(e) => updateTicket(index, 'min_per_order', e.target.value)}
                                            error={fieldError(index, 'min_per_order')}
                                            type="number"
                                            min={1}
                                            required
                                        />
                                        <Input
                                            label={t.ticket_max_label}
                                            value={ticket.max_per_order}
                                            onChange={(e) => updateTicket(index, 'max_per_order', e.target.value)}
                                            error={fieldError(index, 'max_per_order')}
                                            type="number"
                                            min={1}
                                            required
                                        />
                                    </div>

                                    <Textarea
                                        label={t.ticket_description_label}
                                        placeholder={t.ticket_description_placeholder}
                                        value={ticket.description}
                                        onChange={(e) => updateTicket(index, 'description', e.target.value)}
                                        error={fieldError(index, 'description')}
                                        maxLength={1000}
                                        rows={2}
                                    />

                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <label className="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={ticket.is_active}
                                                onChange={(e) => updateTicket(index, 'is_active', e.target.checked)}
                                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30"
                                            />
                                            <span className="text-sm text-slate-600">{t.ticket_active_label}</span>
                                        </label>
                                        <button
                                            type="button"
                                            onClick={() => toggleTicketWindow(index)}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-indigo-600 transition-colors"
                                        >
                                            {t.ticket_window_label}
                                            <ChevronDown size={14} className={windowOpen ? 'rotate-180 transition-transform' : 'transition-transform'} />
                                        </button>
                                    </div>

                                    {windowOpen && (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <Input
                                                label={t.ticket_sale_starts_label}
                                                type="datetime-local"
                                                value={ticket.sale_starts}
                                                onChange={(e) => updateTicket(index, 'sale_starts', e.target.value)}
                                                error={fieldError(index, 'sale_starts')}
                                            />
                                            <Input
                                                label={t.ticket_sale_ends_label}
                                                type="datetime-local"
                                                value={ticket.sale_ends}
                                                onChange={(e) => updateTicket(index, 'sale_ends', e.target.value)}
                                                error={fieldError(index, 'sale_ends')}
                                            />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {(errors as unknown as Record<string, string>).tickets && (
                        <p className="text-xs font-medium text-red-500">{t.ticket_error_required}</p>
                    )}
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

                {/* WhatsApp ticket delivery */}
                <section className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-3">
                    <label className="flex items-start gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.whatsapp_enabled}
                            onChange={(e) => setData('whatsapp_enabled', e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                        />
                        <span>
                            <span className="block text-sm font-semibold text-slate-900">{t.whatsapp_label}</span>
                            <span className="block text-xs text-slate-500 mt-0.5">{t.whatsapp_hint}</span>
                        </span>
                    </label>
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
