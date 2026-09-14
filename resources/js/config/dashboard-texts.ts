export const dashboardTexts = {
    id: {
        header: {
            title: 'Dashboard',
            subtitle: 'Selamat datang kembali, {name}.',
            create_event: 'Buat Event',
            whatsapp: 'Kuota WhatsApp',
        },
        organizer: {
            active_events: 'Event Aktif',
            tickets_sold: 'Tiket Terjual',
            revenue: 'Total Pendapatan',
            drafts: '{count} draft',
            reserved: '{count} tereservasi',
            confirmed: '{count} booking terkonfirmasi',
        },
        attendee: {
            upcoming: 'Booking Mendatang',
            tickets: 'Tiket Saya',
            spent: 'Total Belanja',
        },
        activity: {
            title: 'Aktivitas Terbaru',
            empty_title: 'Belum Ada Aktivitas',
            empty_desc: 'Booking dan penjualan tiket akan muncul di sini secara real-time.',
        },
        status: {
            pending: 'Menunggu',
            confirmed: 'Terkonfirmasi',
            cancelled: 'Dibatalkan',
            refunded: 'Dana Kembali',
        },
        time: {
            now: 'baru saja',
            minutes: 'm lalu',
            hours: 'j lalu',
            days: 'hr lalu',
        },
    },
    en: {
        header: {
            title: 'Dashboard',
            subtitle: 'Welcome back, {name}.',
            create_event: 'Create Event',
            whatsapp: 'WhatsApp Quota',
        },
        organizer: {
            active_events: 'Active Events',
            tickets_sold: 'Tickets Sold',
            revenue: 'Total Revenue',
            drafts: '{count} draft',
            reserved: '{count} reserved',
            confirmed: '{count} confirmed bookings',
        },
        attendee: {
            upcoming: 'Upcoming Bookings',
            tickets: 'My Tickets',
            spent: 'Total Spent',
        },
        activity: {
            title: 'Recent Activity',
            empty_title: 'No Activity Yet',
            empty_desc: 'Bookings and ticket sales will show up here in real-time.',
        },
        status: {
            pending: 'Pending',
            confirmed: 'Confirmed',
            cancelled: 'Cancelled',
            refunded: 'Refunded',
        },
        time: {
            now: 'just now',
            minutes: 'm ago',
            hours: 'h ago',
            days: 'd ago',
        },
    },
};

export type DashboardLanguage = 'id' | 'en';
export const defaultDashboardLanguage: DashboardLanguage = 'en';
