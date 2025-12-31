export type Locale = 'id' | 'en';

export interface TranslationSchema {
    nav: {
        features: string;
        events: string;
        pricing: string;
        login: string;
        signup: string;
    };
    hero: {
        badge: string;
        title_start: string;
        title_highlight: string;
        subtitle: string;
        cta_primary: string;
        cta_secondary: string;
        trusted_by: string;
    };
    stats: {
        events: string;
        tickets: string;
        success: string;
        users: string;
    };
    features: {
        badge: string;
        title: string;
        subtitle: string;
        items: Array<{ title: string; desc: string }>;
    };
    events: {
        badge: string;
        title: string;
        view_all: string;
        items: Array<{ title: string; loc: string; date: string; price: string; cat: string }>;
    };
    pricing: {
        title: string;
        subtitle: string;
        monthly: string;
        cta: string;
        plans: Array<{ name: string; price: string; items: string[] }>;
    };
    cta: {
        title: string;
        subtitle: string;
        primary: string;
        secondary: string;
    };
    footer: {
        desc: string;
        platform: string;
        company: string;
        support: string;
        copyright: string;
    };
}

export const translations: Record<Locale, TranslationSchema> = {
    id: {
        nav: {
            features: 'Fitur',
            events: 'Event Populer',
            pricing: 'Harga',
            login: 'Masuk',
            signup: 'Daftar Gratis',
        },
        hero: {
            badge: 'Platform Event Generasi Baru',
            title_start: 'Ciptakan Momen yang ',
            title_highlight: 'Berarti.',
            subtitle: 'Platform all-in-one untuk merencanakan, mengelola, dan mengembangkan event Anda dengan presisi profesional. Terhubung dengan audiens Anda tidak seperti sebelumnya.',
            cta_primary: 'Mulai Mengatur',
            cta_secondary: 'Jelajahi Event',
            trusted_by: 'DIPERCAYA OLEH 10,000+ PENYELENGGARA',
        },
        stats: {
            events: 'Event terlaksana',
            tickets: 'Tiket Terjual',
            success: 'Tingkat Keberhasilan',
            users: 'Pengguna Puas',
        },
        features: {
            badge: 'Fitur Unggulan',
            title: 'Semua yang Anda butuhkan untuk event sukses.',
            subtitle: 'Berhenti menggunakan banyak alat. Kami membangun pusat komando terbaik untuk penyelenggara profesional.',
            items: [
                { title: 'Penjadwalan Cepat', desc: 'Siapkan event satu atau beberapa hari dalam hitungan menit dengan antarmuka kalender intuitif kami.' },
                { title: 'Manajemen Pengguna', desc: 'Kontrol Akses Berbasis Peran tingkat lanjut untuk mengelola staf, penyelenggara, dan peserta dengan aman.' },
                { title: 'Sistem Tiket', desc: 'Hasilkan tiket berbasis QR dan kelola kapasitas dengan pelacakan reservasi real-time.' },
                { title: 'Keamanan Enterprise', desc: 'Enkripsi tingkat bank untuk semua transaksi dan perlindungan data peserta yang sensitif.' },
                { title: 'Analitik Real-time', desc: 'Pantau penjualan tiket, kehadiran, dan pendapatan secara real-time dengan dashboard yang indah.' },
                { title: 'Pengingat Cerdas', desc: 'Notifikasi otomatis berbasis email dan OTP untuk memberi informasi kepada peserta Anda.' },
            ],
        },
        events: {
            badge: 'Sedang Tren',
            title: 'Jangan lewatkan \npengalaman terbaru.',
            view_all: 'Lihat Semua Event',
            items: [
                {
                    cat: 'Musika',
                    title: 'Neon Underground: Konser Live 2025',
                    loc: 'Stadion Internasional Jakarta',
                    date: '31 Des 2025',
                    price: 'Rp 1.500.000'
                },
                {
                    cat: 'Teknologi',
                    title: 'Innovate 2025: Masa Depan Teknologi Global',
                    loc: 'Singapore Expo Hall 4',
                    date: '15 Jan 2026',
                    price: 'Gratis'
                }
            ],
        },
        pricing: {
            title: 'Harga yang sederhana & transparan.',
            subtitle: 'Pilih paket yang tepat untuk organisasi Anda.',
            monthly: '/bulan',
            cta: 'Mulai Sekarang',
            plans: [
                { name: 'Starter', price: 'Rp 0', items: ['Hosting Event Dasar', 'Hingga 50 Peserta', 'Dukungan Email Standar'] },
                { name: 'Professional', price: 'Rp 750rb', items: ['Event Tanpa Batas', 'Manajemen Berbasis Peran', 'Tiket QR Kustom', 'Analitik Lanjutan', 'Dukungan Prioritas'] },
                { name: 'Enterprise', price: 'Kustom', items: ['Manajer Khusus', 'Akses API Kustom', 'Garansi SLA', 'White-labeling', 'Dukungan On-site'] },
            ],
        },
        cta: {
            title: 'Siap menyelenggarakan \nevent besar Anda?',
            subtitle: 'Bergabunglah dengan ribuan penyelenggara profesional yang mempercayai Evento untuk memberikan pengalaman yang tak terlupakan.',
            primary: 'Buat Event Anda',
            secondary: 'Hubungi Sales',
        },
        footer: {
            desc: 'Memberdayakan penyelenggara event di seluruh dunia dengan teknologi mutakhir dan pengalaman yang mulus.',
            platform: 'Platform',
            company: 'Perusahaan',
            support: 'Dukungan',
            copyright: '© 2025 SISTEM EVENTO. DIDESAIN UNTUK KEUNGGULAN.',
        },
    },
    en: {
        nav: {
            features: 'Features',
            events: 'Popular Events',
            pricing: 'Pricing',
            login: 'Login',
            signup: 'Sign Up Free',
        },
        hero: {
            badge: 'Next-Gen Event Platform',
            title_start: 'Create Moments ',
            title_highlight: 'That Matter.',
            subtitle: 'The all-in-one platform to plan, manage, and scale your events with professional precision. Connect with your audience like never before.',
            cta_primary: 'Start Organizing',
            cta_secondary: 'Explore Events',
            trusted_by: 'TRUSTED BY 10,000+ ORGANIZERS',
        },
        stats: {
            events: 'Events Hosted',
            tickets: 'Tickets Sold',
            success: 'Success Rate',
            users: 'Happy Users',
        },
        features: {
            badge: 'Powerful Features',
            title: 'Everything you need to host successful events.',
            subtitle: 'Stop juggling multiple tools. We\'ve built the ultimate command center for professional organizers.',
            items: [
                { title: 'Quick Scheduling', desc: 'Set up single or multi-day events in minutes with our intuitive calendar interface.' },
                { title: 'User Management', desc: 'Advanced Role-Based Access Control to manage staff, organizers, and attendees securely.' },
                { title: 'Ticketing System', desc: 'Generate QR-based tickets and manage capacity with real-time reservation tracking.' },
                { title: 'Enterprise Security', desc: 'Bank-grade encryption for all transactions and sensitive attendee data protection.' },
                { title: 'Real-time Analytics', desc: 'Monitor ticket sales, attendance, and revenue in real-time with beautiful dashboards.' },
                { title: 'Smart Reminders', desc: 'Automatic email and OTP-based notifications to keep your attendees informed.' },
            ],
        },
        events: {
            badge: 'Trending Now',
            title: 'Don\'t miss out on \nthe latest experiences.',
            view_all: 'View All Events',
            items: [
                {
                    cat: 'Music',
                    title: 'Neon Underground: Live Concert 2025',
                    loc: 'Jakarta International Stadium',
                    date: 'Dec 31, 2025',
                    price: '$120'
                },
                {
                    cat: 'Tech',
                    title: 'Innovate 2025: Future of Global Technology',
                    loc: 'Singapore Expo Hall 4',
                    date: 'Jan 15, 2026',
                    price: 'Free'
                }
            ],
        },
        pricing: {
            title: 'Simple, transparent pricing.',
            subtitle: 'Choose the plan that\'s right for your organization.',
            monthly: '/month',
            cta: 'Get Started',
            plans: [
                { name: 'Starter', price: '$0', items: ['Basic Event Hosting', 'Up to 50 Attendees', 'Standard Email Support'] },
                { name: 'Professional', price: '$49', items: ['Unlimited Events', 'Role-Based Management', 'Custom QR Tickets', 'Advanced Analytics', 'Priority Support'] },
                { name: 'Enterprise', price: 'Custom', items: ['Dedicated Manager', 'Custom API Access', 'SLA Guarantee', 'White-labeling', 'On-site Support'] },
            ],
        },
        cta: {
            title: 'Ready to host your \nnext big event?',
            subtitle: 'Join thousands of professional organizers who trust Evento to deliver unforgettable experiences.',
            primary: 'Create Your Event',
            secondary: 'Talk to Sales',
        },
        footer: {
            desc: 'Empowering event organizers worldwide with cutting-edge technology and seamless experiences.',
            platform: 'Platform',
            company: 'Company',
            support: 'Support',
            copyright: '© 2025 EVENTO SYSTEM. DESIGNED FOR EXCELLENCE.',
        },
    },
};
