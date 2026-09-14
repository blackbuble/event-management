import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { eventTexts, EventLanguage, defaultEventLanguage } from '@/config/event-texts';
import {
    ArrowLeft,
    Calendar,
    Clock,
    MapPin,
    Video,
    Globe2,
    Users,
    Ticket,
    ExternalLink,
    Pencil,
    Share2,
    Minus,
    Plus,
    ShoppingCart,
    AlertCircle,
    CheckCircle2,
    Star,
    Tag,
} from 'lucide-react';

interface TicketItem {
    id: number;
    name: string;
    description: string | null;
    price: number;
    remaining: number;
    min_per_order: number;
    max_per_order: number;
}

interface OrganizerRating {
    average: number | null;
    count: number;
}

interface PastEvent {
    id: number;
    title: string;
    slug: string;
    type: 'online' | 'offline' | 'hybrid';
    image_url: string | null;
    start_date: string | null;
}

interface Organizer {
    id: number;
    name: string;
    rating: OrganizerRating;
    past_events: PastEvent[];
}

interface AttendeeAvatar {
    initials: string;
    avatar_url: string | null;
}

interface Attendees {
    count: number;
    avatars: AttendeeAvatar[];
}

interface MyReview {
    rating: number;
    comment: string | null;
    created_at: string | null;
}

interface EventData {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    type: 'online' | 'offline' | 'hybrid';
    category: string | null;
    category_label: string | null;
    status: 'draft' | 'published' | 'cancelled';
    image_url: string | null;
    venue_name: string | null;
    venue_address: string | null;
    city: string | null;
    meeting_link: string | null;
    latitude: number | null;
    longitude: number | null;
    start_date: string | null;
    end_date: string | null;
    capacity: number | null;
    is_full: boolean;
    organizer: Organizer | null;
    attendees: Attendees;
    tickets: TicketItem[];
    can_book: boolean;
    can_review: boolean;
    my_review: MyReview | null;
}

interface Props {
    event: EventData;
    is_owner: boolean;
}

const typeIcons = { offline: MapPin, online: Video, hybrid: Globe2 } as const;

function formatDate(iso: string | null, lang: EventLanguage, tz: string): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(lang === 'id' ? 'id-ID' : 'en-US', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: tz,
    });
}

function formatTime(iso: string | null, lang: EventLanguage, tz: string): string {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString(lang === 'id' ? 'id-ID' : 'en-US', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: tz,
    });
}

function formatPrice(price: number): string {
    if (price === 0) return '';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);
}

function getDurationHours(start: string | null, end: string | null): number | null {
    if (!start || !end) return null;
    const diff = new Date(end).getTime() - new Date(start).getTime();
    const hours = diff / (1000 * 60 * 60);
    return hours > 0 ? Math.round(hours) : null;
}

function renderMarkdown(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/^### (.+)$/gm, '<h4 class="font-semibold text-slate-900 mt-5 mb-2 text-base">$1</h4>')
        .replace(/^## (.+)$/gm, '<h3 class="font-bold text-slate-900 mt-6 mb-2 text-lg">$1</h3>')
        .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-slate-900">$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^---$/gm, '<hr class="my-4 border-slate-200" />')
        .replace(/^\* (.+)$/gm, '<li class="ml-4 list-disc text-slate-600">$1</li>')
        .replace(/^- (.+)$/gm, '<li class="ml-4 list-disc text-slate-600">$1</li>')
        .replace(/\n{2,}/g, '</p><p class="text-slate-600 leading-relaxed mb-3">')
        .replace(/\n/g, '<br />')
        .replace(/\|.+\|/g, (match) => {
            if (match.match(/^\|[\s-|]+\|$/)) return '';
            const cells = match.split('|').filter(Boolean).map((c) => c.trim());
            return '<div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600 my-1">' +
                cells.map((c) => `<span>${c}</span>`).join('') + '</div>';
        });
}

function ShareButton() {
    const handleShare = async () => {
        if (navigator.share) {
            await navigator.share({ url: window.location.href });
        } else {
            await navigator.clipboard.writeText(window.location.href);
        }
    };

    return (
        <button
            onClick={handleShare}
            className="inline-flex items-center justify-center h-10 w-10 rounded-full border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors"
        >
            <Share2 className="h-4 w-4" />
        </button>
    );
}

export default function ShowEvent({ event, is_owner }: Props) {
    const { locale, auth, flash, timezone } = usePage().props as any;
    const lang = (locale as EventLanguage) || defaultEventLanguage;
    const tz = (timezone as string) || 'Asia/Jakarta';
    const t = eventTexts[lang].show;
    const isAuthenticated = !!auth?.user;

    const [scrolled, setScrolled] = useState(false);
    const [reviewRating, setReviewRating] = useState(event.my_review?.rating ?? 0);
    const [reviewComment, setReviewComment] = useState(event.my_review?.comment ?? '');
    const [submittingReview, setSubmittingReview] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 140);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const submitReview = (e: React.FormEvent) => {
        e.preventDefault();
        if (reviewRating < 1) return;

        setSubmittingReview(true);
        router.post(
            `/events/${event.id}/reviews`,
            { rating: reviewRating, comment: reviewComment },
            { preserveScroll: true, onFinish: () => setSubmittingReview(false) },
        );
    };

    const TypeIcon = typeIcons[event.type] ?? Globe2;
    const isDraft = event.status === 'draft';
    const isCancelled = event.status === 'cancelled';
    const duration = getDurationHours(event.start_date, event.end_date);
    const hasTickets = event.tickets.length > 0;
    const hasMap = event.latitude !== null && event.longitude !== null;
    const isEventEnded = event.end_date ? new Date(event.end_date) < new Date() : false;
    // Seamless checkout: guests may buy without an account. Only the event
    // organizer is blocked (server-enforced too).
    const isBlockedOwner = isAuthenticated && !event.can_book;
    const canBook = !isBlockedOwner;

    const [quantities, setQuantities] = useState<Record<number, number>>({});

    const updateQuantity = (ticketId: number, delta: number, max: number, min: number, remaining: number) => {
        setQuantities((prev) => {
            const current = prev[ticketId] || 0;
            const next = Math.max(0, Math.min(current + delta, remaining, max || remaining));
            if (next === 0) {
                const { [ticketId]: _, ...rest } = prev;
                return rest;
            }
            return { ...prev, [ticketId]: next };
        });
    };

    const selectedCount = Object.values(quantities).reduce((sum, q) => sum + q, 0);
    const totalPrice = event.tickets.reduce(
        (sum, tk) => sum + (quantities[tk.id] || 0) * tk.price,
        0,
    );

    const [processing, setProcessing] = useState(false);

    const handleBook = () => {
        const tickets = Object.entries(quantities)
            .filter(([, qty]) => qty > 0)
            .map(([ticketId, quantity]) => ({ ticket_id: Number(ticketId), quantity }));

        if (tickets.length === 0) return;

        // Head to the transaction page to capture attendee names before checkout.
        // `indices` keeps the query as tickets[0][ticket_id]=..&tickets[0][quantity]=..
        // (the default `brackets` format emits tickets[][...] which PHP splits into
        // separate rows and the server then sees no complete selection).
        setProcessing(true);
        router.get(route('bookings.create', event.id), { tickets }, {
            preserveScroll: true,
            queryStringArrayFormat: 'indices',
            onFinish: () => setProcessing(false),
        });
    };

    const descriptionHtml = useMemo(
        () => (event.description ? renderMarkdown(event.description) : ''),
        [event.description],
    );

    return (
        <>
            <Head title={event.title} />

            <div className="min-h-screen bg-white">
                {/* Navbar */}
                <header className="sticky top-0 z-30 bg-white/90 backdrop-blur-md border-b border-slate-100">
                    <div className="mx-auto max-w-6xl px-4 sm:px-6 h-14 flex items-center justify-between gap-3">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900 transition-colors shrink-0"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            <span className="hidden sm:inline">{t.back_home}</span>
                        </Link>

                        {/* Event name + date, revealed while scrolling */}
                        <div
                            className={`min-w-0 flex-1 text-center transition-all duration-300 ${
                                scrolled ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-1 pointer-events-none'
                            }`}
                            aria-hidden={!scrolled}
                        >
                            <p className="truncate text-sm font-semibold text-slate-900 leading-tight">
                                {event.title}
                            </p>
                            <p className="truncate text-xs text-slate-500">
                                {formatDate(event.start_date, lang, tz)}
                                {event.start_date && ` • ${formatTime(event.start_date, lang, tz)}`}
                            </p>
                        </div>

                        <div className="flex items-center gap-2 shrink-0">
                            <ShareButton />
                            {is_owner && (
                                <Link
                                    href={`/dashboard/events/${event.id}/edit`}
                                    className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition-colors"
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                    <span className="hidden sm:inline">{t.edit_event}</span>
                                </Link>
                            )}
                        </div>
                    </div>
                </header>

                {/* Flash messages */}
                {(flash?.message || flash?.error) && (
                    <div className="mx-auto max-w-6xl px-4 sm:px-6 pt-4">
                        {flash.message && (
                            <div className="flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                                <CheckCircle2 className="h-5 w-5 shrink-0" />
                                {flash.message}
                            </div>
                        )}
                        {flash.error && (
                            <div className="flex items-center gap-3 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                                <AlertCircle className="h-5 w-5 shrink-0" />
                                {flash.error}
                            </div>
                        )}
                    </div>
                )}

                {/* Hero banner */}
                {event.image_url && (
                    <div className="w-full bg-slate-900">
                        <div className="mx-auto max-w-6xl">
                            <img
                                src={event.image_url}
                                alt={event.title}
                                className="w-full h-56 sm:h-72 lg:h-96 object-cover"
                            />
                        </div>
                    </div>
                )}

                {/* Main content — two-column */}
                <div className="mx-auto max-w-6xl px-4 sm:px-6 py-8 lg:py-10">
                    <div className="lg:grid lg:grid-cols-[1fr_380px] lg:gap-10">
                        {/* Left column */}
                        <div className="space-y-8">
                            {/* Title + badges */}
                            <div className="space-y-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                        <TypeIcon className="h-3.5 w-3.5" />
                                        {t[`type_${event.type}` as keyof typeof t]}
                                    </span>
                                    {event.category_label && (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                                            <Tag className="h-3.5 w-3.5" />
                                            {event.category_label}
                                        </span>
                                    )}
                                    {isDraft && (
                                        <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                            {t.draft_badge}
                                        </span>
                                    )}
                                    {isCancelled && (
                                        <span className="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                                            {t.cancelled_badge}
                                        </span>
                                    )}
                                </div>
                                <h1 className="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900 tracking-tight leading-tight">
                                    {event.title}
                                </h1>
                            </div>

                            {/* Organizer — lu.ma style */}
                            {event.organizer && (
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center justify-center h-10 w-10 rounded-full bg-gradient-to-br from-slate-700 to-slate-900 text-white text-sm font-bold">
                                        {event.organizer.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <p className="text-xs text-slate-500">{t.organized_by}</p>
                                        <p className="text-sm font-semibold text-slate-900">{event.organizer.name}</p>
                                    </div>
                                </div>
                            )}

                            {/* Attendees — avatar stack */}
                            {event.attendees && event.attendees.count > 0 && (
                                <div className="flex items-center gap-3">
                                    <div className="flex -space-x-2">
                                        {event.attendees.avatars.map((a, i) => (
                                            <div
                                                key={i}
                                                className="h-9 w-9 rounded-full border-2 border-white bg-slate-200 overflow-hidden flex items-center justify-center text-[11px] font-bold text-slate-600 shrink-0"
                                                title={a.initials}
                                            >
                                                {a.avatar_url ? (
                                                    <img
                                                        src={a.avatar_url}
                                                        alt={a.initials}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    a.initials
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                    <p className="text-sm text-slate-600">
                                        {t.attendees_registered.replace('{count}', String(event.attendees.count))}
                                    </p>
                                </div>
                            )}

                            {/* Date & Time */}
                            {(event.start_date || event.end_date) && (
                                <div className="flex items-start gap-4 p-4 rounded-xl bg-slate-50">
                                    <div className="flex flex-col items-center justify-center h-14 w-14 rounded-xl bg-white border border-slate-200 shrink-0">
                                        <span className="text-[10px] font-bold uppercase text-rose-500 leading-none">
                                            {event.start_date
                                                ? new Date(event.start_date).toLocaleDateString(
                                                      lang === 'id' ? 'id-ID' : 'en-US',
                                                      { month: 'short', timeZone: tz },
                                                  )
                                                : ''}
                                        </span>
                                        <span className="text-xl font-bold text-slate-900 leading-none mt-0.5">
                                            {event.start_date ? new Date(event.start_date).getDate() : ''}
                                        </span>
                                    </div>
                                    <div className="text-sm">
                                        <p className="font-semibold text-slate-900">
                                            {formatDate(event.start_date, lang, tz)}
                                        </p>
                                        <p className="text-slate-500 mt-0.5">
                                            {formatTime(event.start_date, lang, tz)}
                                            {event.end_date && ` - ${formatTime(event.end_date, lang, tz)}`}
                                        </p>
                                        {duration !== null && (
                                            <p className="inline-flex items-center gap-1 text-slate-400 mt-1">
                                                <Clock className="h-3.5 w-3.5" />
                                                {duration} {lang === 'id' ? 'jam' : duration === 1 ? 'hour' : 'hours'}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Venue */}
                            {event.venue_name && event.type !== 'online' && (
                                <div className="flex items-start gap-4 p-4 rounded-xl bg-slate-50">
                                    <div className="flex items-center justify-center h-14 w-14 rounded-xl bg-white border border-slate-200 shrink-0">
                                        <MapPin className="h-6 w-6 text-slate-400" />
                                    </div>
                                    <div className="text-sm">
                                        <p className="font-semibold text-slate-900">{event.venue_name}</p>
                                        {event.venue_address && (
                                            <p className="text-slate-500 mt-0.5">{event.venue_address}</p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Meeting link */}
                            {event.meeting_link && event.type !== 'offline' && (
                                <div className="flex items-start gap-4 p-4 rounded-xl bg-slate-50">
                                    <div className="flex items-center justify-center h-14 w-14 rounded-xl bg-white border border-slate-200 shrink-0">
                                        <Video className="h-6 w-6 text-slate-400" />
                                    </div>
                                    <div className="text-sm">
                                        <p className="font-semibold text-slate-900">{t.meeting_link}</p>
                                        <a
                                            href={event.meeting_link}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 mt-0.5 transition-colors"
                                        >
                                            {t.join} <ExternalLink className="h-3.5 w-3.5" />
                                        </a>
                                    </div>
                                </div>
                            )}

                            {/* Highlights bar */}
                            <div className="flex flex-wrap gap-3">
                                {duration !== null && (
                                    <span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">
                                        <Clock className="h-3.5 w-3.5" />
                                        {duration} {lang === 'id' ? 'jam' : duration === 1 ? 'hour' : 'hours'}
                                    </span>
                                )}
                                <span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">
                                    <TypeIcon className="h-3.5 w-3.5" />
                                    {event.type === 'offline'
                                        ? lang === 'id'
                                            ? 'Tatap Muka'
                                            : 'In Person'
                                        : event.type === 'online'
                                          ? 'Online'
                                          : 'Hybrid'}
                                </span>
                                {event.capacity && (
                                    <span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">
                                        <Users className="h-3.5 w-3.5" />
                                        {t.capacity_value.replace('{count}', String(event.capacity))}
                                    </span>
                                )}
                            </div>

                            {/* Description */}
                            {event.description && (
                                <div className="space-y-2">
                                    <h2 className="text-lg font-semibold text-slate-900">
                                        {lang === 'id' ? 'Tentang Event' : 'About Event'}
                                    </h2>
                                    <div
                                        className="text-slate-600 leading-relaxed text-[15px]"
                                        dangerouslySetInnerHTML={{
                                            __html: `<p class="text-slate-600 leading-relaxed mb-3">${descriptionHtml}</p>`,
                                        }}
                                    />
                                </div>
                            )}

                            {/* Map */}
                            {hasMap && event.type !== 'online' && (
                                <div className="space-y-3">
                                    <h2 className="text-lg font-semibold text-slate-900">{t.location_title}</h2>
                                    <div className="rounded-xl overflow-hidden border border-slate-200">
                                        <iframe
                                            title="Event Location"
                                            width="100%"
                                            height="300"
                                            style={{ border: 0 }}
                                            loading="lazy"
                                            src={`https://www.openstreetmap.org/export/embed.html?bbox=${event.longitude! - 0.005},${event.latitude! - 0.005},${event.longitude! + 0.005},${event.latitude! + 0.005}&layer=mapnik&marker=${event.latitude},${event.longitude}`}
                                        />
                                        <a
                                            href={`https://www.openstreetmap.org/?mlat=${event.latitude}&mlon=${event.longitude}#map=16/${event.latitude}/${event.longitude}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center justify-center gap-2 py-2.5 bg-slate-50 text-sm text-slate-600 hover:text-slate-900 transition-colors"
                                        >
                                            <ExternalLink className="h-3.5 w-3.5" />
                                            {t.view_map}
                                        </a>
                                    </div>
                                </div>
                            )}
                            {/* Review — submit or show own review */}
                            {(event.can_review || event.my_review) && (
                                <div className="rounded-2xl border border-slate-200 bg-white p-6 space-y-4">
                                    <h2 className="text-lg font-semibold text-slate-900">
                                        {event.my_review ? t.my_review_title : t.review_title}
                                    </h2>

                                    {event.my_review ? (
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-1">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <Star
                                                        key={star}
                                                        className={`h-5 w-5 ${
                                                            star <= event.my_review!.rating
                                                                ? 'fill-amber-400 text-amber-400'
                                                                : 'text-slate-300'
                                                        }`}
                                                    />
                                                ))}
                                            </div>
                                            {event.my_review.comment && (
                                                <p className="text-sm text-slate-600">{event.my_review.comment}</p>
                                            )}
                                        </div>
                                    ) : (
                                        <form onSubmit={submitReview} className="space-y-4">
                                            <p className="text-sm text-slate-500">{t.review_subtitle}</p>
                                            <div className="flex items-center gap-1">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <button
                                                        key={star}
                                                        type="button"
                                                        onClick={() => setReviewRating(star)}
                                                        aria-label={`${star}`}
                                                        className="p-0.5"
                                                    >
                                                        <Star
                                                            className={`h-7 w-7 transition-colors ${
                                                                star <= reviewRating
                                                                    ? 'fill-amber-400 text-amber-400'
                                                                    : 'text-slate-300 hover:text-amber-300'
                                                            }`}
                                                        />
                                                    </button>
                                                ))}
                                            </div>
                                            <textarea
                                                value={reviewComment}
                                                onChange={(e) => setReviewComment(e.target.value)}
                                                placeholder={t.review_placeholder}
                                                rows={3}
                                                maxLength={1000}
                                                className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-slate-400 focus:ring-4 focus:ring-slate-900/5"
                                            />
                                            <button
                                                type="submit"
                                                disabled={reviewRating < 1 || submittingReview}
                                                className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                            >
                                                {submittingReview ? t.review_submitting : t.review_submit}
                                            </button>
                                        </form>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Right sidebar — sticky */}
                        <aside className="mt-8 lg:mt-0">
                            <div className="lg:sticky lg:top-20 space-y-5">
                                {/* Ticket card */}
                                <div
                                    id="tickets-section"
                                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5"
                                >
                                    <h3 className="flex items-center gap-2 text-base font-semibold text-slate-900">
                                        <Ticket className="h-5 w-5" />
                                        {t.tickets_title}
                                    </h3>

                                    {!hasTickets ? (
                                        <p className="text-sm text-slate-500">{t.no_tickets}</p>
                                    ) : (
                                        <>
                                            <div className="space-y-3">
                                                {event.tickets.map((ticket) => {
                                                    const isSoldOut = ticket.remaining <= 0;
                                                    const qty = quantities[ticket.id] || 0;
                                                    const maxQty = ticket.max_per_order || ticket.remaining;

                                                    return (
                                                        <div
                                                            key={ticket.id}
                                                            className={`rounded-xl border p-4 transition-colors ${
                                                                isSoldOut
                                                                    ? 'border-slate-100 bg-slate-50 opacity-60'
                                                                    : qty > 0
                                                                      ? 'border-blue-200 bg-blue-50/30'
                                                                      : 'border-slate-200 hover:border-slate-300'
                                                            }`}
                                                        >
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div className="min-w-0">
                                                                    <p className="font-semibold text-sm text-slate-900">
                                                                        {ticket.name}
                                                                    </p>
                                                                    {ticket.description && (
                                                                        <p className="text-xs text-slate-500 mt-0.5 line-clamp-2">
                                                                            {ticket.description}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                                <p className="text-sm font-bold text-slate-900 whitespace-nowrap">
                                                                    {ticket.price === 0
                                                                        ? t.free
                                                                        : formatPrice(ticket.price)}
                                                                </p>
                                                            </div>

                                                            <div className="mt-3 flex items-center justify-between">
                                                                <div className="flex flex-wrap items-center gap-1.5 text-xs">
                                                                    {isSoldOut ? (
                                                                        <span className="rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700">
                                                                            {t.sold_out}
                                                                        </span>
                                                                    ) : (
                                                                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700">
                                                                            {t.remaining.replace(
                                                                                '{count}',
                                                                                String(ticket.remaining),
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                </div>

                                                                {/* Quantity selector */}
                                                                {!isSoldOut && !isEventEnded && !event.is_full && canBook && (
                                                                    <div className="flex items-center gap-1">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                updateQuantity(
                                                                                    ticket.id,
                                                                                    -1,
                                                                                    maxQty,
                                                                                    ticket.min_per_order || 1,
                                                                                    ticket.remaining,
                                                                                )
                                                                            }
                                                                            disabled={qty === 0}
                                                                            className="h-7 w-7 rounded-md border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                                                        >
                                                                            <Minus className="h-3.5 w-3.5" />
                                                                        </button>
                                                                        <span className="w-8 text-center text-sm font-medium text-slate-900">
                                                                            {qty}
                                                                        </span>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                updateQuantity(
                                                                                    ticket.id,
                                                                                    1,
                                                                                    maxQty,
                                                                                    ticket.min_per_order || 1,
                                                                                    ticket.remaining,
                                                                                )
                                                                            }
                                                                            disabled={qty >= Math.min(maxQty, ticket.remaining)}
                                                                            className="h-7 w-7 rounded-md border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                                                        >
                                                                            <Plus className="h-3.5 w-3.5" />
                                                                        </button>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>

                                            {/* Total + Book button */}
                                            {selectedCount > 0 && (
                                                <div className="border-t border-slate-200 pt-4 space-y-3">
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className="text-slate-600">
                                                            {t.total} ({selectedCount})
                                                        </span>
                                                        <span className="text-lg font-bold text-slate-900">
                                                            {totalPrice === 0
                                                                ? t.free
                                                                : formatPrice(totalPrice)}
                                                        </span>
                                                    </div>

                                                    {canBook ? (
                                                        <button
                                                            type="button"
                                                            onClick={handleBook}
                                                            disabled={processing}
                                                            className="w-full rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50 transition-colors flex items-center justify-center gap-2"
                                                        >
                                                            <ShoppingCart className="h-4 w-4" />
                                                            {processing ? t.booking : t.book_now}
                                                        </button>
                                                    ) : (
                                                        <p className="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-center text-xs font-medium text-amber-800">
                                                            {t.organizer_cannot_book}
                                                        </p>
                                                    )}
                                                </div>
                                            )}

                                            {selectedCount === 0 && !event.is_full && !isEventEnded && (
                                                isBlockedOwner ? (
                                                    <p className="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-center text-xs font-medium text-amber-800">
                                                        {t.organizer_cannot_book}
                                                    </p>
                                                ) : (
                                                    <p className="text-xs text-slate-400 text-center">
                                                        {t.select_tickets}
                                                    </p>
                                                )
                                            )}

                                            {event.is_full && (
                                                <p className="text-sm font-medium text-red-600 text-center">
                                                    {t.event_full}
                                                </p>
                                            )}

                                            {isEventEnded && (
                                                <p className="text-sm font-medium text-slate-500 text-center">
                                                    {t.event_ended}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </div>

                                {/* Capacity card */}
                                {event.capacity && (
                                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <div className="flex items-center gap-3">
                                            <Users className="h-5 w-5 text-slate-400" />
                                            <div className="text-sm">
                                                <span className="font-medium text-slate-700">{t.capacity}</span>
                                                <span className="text-slate-500 ml-2">
                                                    {t.capacity_value.replace('{count}', String(event.capacity))}
                                                </span>
                                                {event.is_full && (
                                                    <span className="ml-2 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">
                                                        {t.full}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Organizer card — sidebar */}
                                {event.organizer && (
                                    <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                                        <p className="text-xs text-slate-500">{t.organized_by}</p>
                                        <div className="flex items-center gap-3">
                                            <div className="flex items-center justify-center h-10 w-10 rounded-full bg-gradient-to-br from-slate-700 to-slate-900 text-white text-sm font-bold shrink-0">
                                                {event.organizer.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="font-semibold text-sm text-slate-900 truncate">
                                                    {event.organizer.name}
                                                </p>
                                                {event.organizer.rating.count > 0 ? (
                                                    <div className="flex items-center gap-1.5 mt-0.5">
                                                        <span className="flex items-center">
                                                            {[1, 2, 3, 4, 5].map((star) => (
                                                                <Star
                                                                    key={star}
                                                                    className={`h-3.5 w-3.5 ${
                                                                        event.organizer!.rating.average !== null &&
                                                                        star <= Math.round(event.organizer!.rating.average)
                                                                            ? 'fill-amber-400 text-amber-400'
                                                                            : 'text-slate-300'
                                                                    }`}
                                                                />
                                                            ))}
                                                        </span>
                                                        <span className="text-xs text-slate-500">
                                                            {event.organizer.rating.average} ({t.rating_count.replace('{count}', String(event.organizer.rating.count))})
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <p className="text-xs text-slate-400 mt-0.5">{t.no_rating}</p>
                                                )}
                                            </div>
                                        </div>

                                        {/* Past events */}
                                        {event.organizer.past_events.length > 0 && (
                                            <div className="border-t border-slate-100 pt-4 space-y-3">
                                                <p className="text-xs font-semibold text-slate-700">{t.past_events}</p>
                                                <div className="space-y-3">
                                                    {event.organizer.past_events.map((past) => (
                                                        <Link
                                                            key={past.id}
                                                            href={`/events/${past.slug}`}
                                                            className="flex items-center gap-3 group"
                                                        >
                                                            <div className="h-9 w-9 rounded-lg bg-slate-100 overflow-hidden shrink-0">
                                                                {past.image_url ? (
                                                                    <img
                                                                        src={past.image_url}
                                                                        alt={past.title}
                                                                        className="h-full w-full object-cover"
                                                                    />
                                                                ) : (
                                                                    <div className="h-full w-full flex items-center justify-center">
                                                                        <Calendar className="h-4 w-4 text-slate-400" />
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="min-w-0">
                                                                <p className="text-xs font-medium text-slate-800 truncate group-hover:text-slate-950">
                                                                    {past.title}
                                                                </p>
                                                                <p className="text-[11px] text-slate-400">
                                                                    {formatDate(past.start_date, lang, tz)}
                                                                </p>
                                                            </div>
                                                        </Link>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </aside>
                    </div>
                </div>

                {/* Mobile sticky bottom bar */}
                {hasTickets && !isEventEnded && !event.is_full && (
                    <div className="fixed bottom-0 inset-x-0 lg:hidden bg-white border-t border-slate-200 px-4 py-3 z-20">
                        <div className="flex items-center justify-between">
                            <div>
                                {selectedCount > 0 ? (
                                    <p className="text-sm font-bold text-slate-900">
                                        {totalPrice === 0 ? t.free : formatPrice(totalPrice)}
                                        <span className="text-slate-400 font-normal ml-1">
                                            ({selectedCount})
                                        </span>
                                    </p>
                                ) : (
                                    <p className="text-sm text-slate-500">{t.select_tickets}</p>
                                )}
                            </div>
                            {selectedCount > 0 && canBook ? (
                                <button
                                    type="button"
                                    onClick={handleBook}
                                    disabled={processing}
                                    className="rounded-lg bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50 transition-colors"
                                >
                                    {processing ? t.booking : t.book_now}
                                </button>
                            ) : (
                                <a
                                    href="#tickets-section"
                                    className="rounded-lg bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition-colors"
                                >
                                    {lang === 'id' ? 'Lihat Tiket' : 'View Tickets'}
                                </a>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
