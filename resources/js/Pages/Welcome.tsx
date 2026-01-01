import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { landingTexts } from '@/config/landing-texts';
import { Language, defaultLanguage } from '@/config/auth-texts';
import {
    Zap, QrCode, BarChart, Globe, ArrowRight,
    Menu, X, Sparkles, Search, MapPin,
    Music, Laptop, Briefcase, Utensils, Palette, Trophy,
    Calendar, Clock, Heart, Share2, Filter, CheckCircle2
} from 'lucide-react';

// Mock Data for Events
const MOCK_EVENTS = [
    { id: 1, title: 'Summer Jazz Festival', category: 'music', location: 'Jakarta', lat: -6.2088, lng: 106.8456, price: 'Rp 250k', date: '20 Jan 2026', image: 'https://images.unsplash.com/photo-1514525253361-bee8a1874298?auto=format&fit=crop&w=800&q=80' },
    { id: 2, title: 'Tech Conference 2026', category: 'tech', location: 'Singapore', lat: 1.3521, lng: 103.8198, price: 'Free', date: '15 Feb 2026', image: 'https://images.unsplash.com/photo-1540575861501-7c00117fb3c8?auto=format&fit=crop&w=800&q=80' },
    { id: 3, title: 'Digital Marketing Summit', category: 'business', location: 'Kuala Lumpur', lat: 3.1390, lng: 101.6869, price: 'Rp 500k', date: '05 Mar 2026', image: 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=800&q=80' },
    { id: 4, title: 'Street Food Mania', category: 'food', location: 'Bangkok', lat: 13.7563, lng: 100.5018, price: 'Rp 50k', date: '12 Apr 2026', image: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=800&q=80' },
    { id: 5, title: 'Contemporary Art Expo', category: 'art', location: 'Jakarta', lat: -6.1751, lng: 106.8650, price: 'Rp 100k', date: '22 May 2026', image: 'https://images.unsplash.com/photo-1460661419201-fd4cecdc8a8b?auto=format&fit=crop&w=800&q=80' },
    { id: 6, title: 'ASEAN Badminton Cup', category: 'sports', location: 'Jakarta', lat: -6.2146, lng: 106.7996, price: 'Rp 150k', date: '10 Jun 2026', image: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80' },
];

export default function Welcome() {
    const { locale, auth } = usePage().props as any;
    const currentLang = (locale as Language) || defaultLanguage;
    const t = landingTexts[currentLang];

    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [userLocation, setUserLocation] = useState<{ lat: number, lng: number } | null>(null);
    const [isDetecting, setIsDetecting] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const getFlagUrl = (isoCode: string) => `https://flagcdn.com/w80/${isoCode.toLowerCase()}.png`;

    const handleDetectLocation = () => {
        setIsDetecting(true);
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition((position) => {
                setUserLocation({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                });
                setIsDetecting(false);
            }, () => {
                setIsDetecting(false);
                alert("Gagal mengakses lokasi. Pastikan izin lokasi aktif.");
            });
        }
    };

    // Calculate distance for nearby sorting (Euclidean distance simplified)
    const filteredEvents = useMemo(() => {
        let events = MOCK_EVENTS.filter(event => {
            const matchesSearch = event.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                event.location.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesCategory = selectedCategory === 'all' || event.category === selectedCategory;
            return matchesSearch && matchesCategory;
        });

        if (userLocation) {
            return events.sort((a, b) => {
                const distA = Math.sqrt(Math.pow(a.lat - userLocation.lat, 2) + Math.pow(a.lng - userLocation.lng, 2));
                const distB = Math.sqrt(Math.pow(b.lat - userLocation.lat, 2) + Math.pow(b.lng - userLocation.lng, 2));
                return distA - distB;
            });
        }
        return events;
    }, [searchTerm, selectedCategory, userLocation]);

    const categories = [
        { id: 'music', label: t.categories.music, icon: Music },
        { id: 'tech', label: t.categories.tech, icon: Laptop },
        { id: 'business', label: t.categories.business, icon: Briefcase },
        { id: 'food', label: t.categories.food, icon: Utensils },
        { id: 'art', label: t.categories.art, icon: Palette },
        { id: 'sports', label: t.categories.sports, icon: Trophy },
    ];

    return (
        <div className="min-h-screen bg-white text-slate-900 selection:bg-indigo-500 selection:text-white transition-colors duration-300">
            <style dangerouslySetInnerHTML={{
                __html: `
                .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); }
                @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .animate-fade-up { animation: fadeUp 0.5s ease-out forwards; }
            `}} />
            <Head title="Evento - Temukan Acara di ASEAN" />

            {/* Navbar */}
            <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${scrolled ? 'bg-white/90 backdrop-blur-md border-b border-slate-100 py-3 shadow-sm' : 'bg-transparent py-5'
                }`}>
                <div className="max-w-7xl mx-auto px-6 flex justify-between items-center">
                    <div className="flex items-center group cursor-pointer">
                        <div className="w-10 h-10 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-indigo-600/20 group-hover:rotate-6 transition-transform">
                            <Sparkles size={20} />
                        </div>
                        <span className="ml-3 text-2xl font-black tracking-tighter">EVENTO</span>
                    </div>

                    <div className="hidden lg:flex items-center space-x-10 font-bold text-sm tracking-tight text-slate-600">
                        <a href="#events" className="hover:text-indigo-600 transition-colors">{t.nav.events}</a>
                        <a href="#how-it-works" className="hover:text-indigo-600 transition-colors">{t.nav.how_it_works}</a>
                        <div className="h-4 w-px bg-slate-200" />
                        <div className="flex bg-slate-100 p-1 rounded-xl">
                            <Link href={route('language.switch', 'id')} className={`px-3 py-1.5 rounded-lg flex items-center ${currentLang === 'id' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500'}`}>
                                <img src={getFlagUrl('ID')} className="w-4 mr-2" alt="ID" /> ID
                            </Link>
                            <Link href={route('language.switch', 'en')} className={`px-3 py-1.5 rounded-lg flex items-center ${currentLang === 'en' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500'}`}>
                                <img src={getFlagUrl('US')} className="w-4 mr-2" alt="US" /> EN
                            </Link>
                        </div>
                        {auth.user ? (
                            <Link href={route('dashboard')} className="px-6 py-2.5 bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-600/20 hover:scale-105 transition-all">
                                {t.nav.dashboard}
                            </Link>
                        ) : (
                            <Link href={route('login')} className="px-6 py-2.5 bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-600/20 hover:scale-105 transition-all">
                                {t.nav.login}
                            </Link>
                        )}
                    </div>
                </div>
            </nav>

            {/* Hero Section - Cleaner White Design */}
            <section className="relative pt-40 pb-20 overflow-hidden">
                <div className="absolute inset-0 -z-10">
                    <div className="absolute top-0 right-0 w-1/3 h-full bg-slate-50 skew-x-12" />
                </div>

                <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <div className="animate-fade-up">
                        <div className="inline-block px-4 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-black tracking-widest uppercase rounded-full mb-6">
                            {t.hero.badge}
                        </div>
                        <h1 className="text-6xl lg:text-8xl font-black leading-[0.9] tracking-tighter mb-8">
                            {t.hero.title}
                        </h1>
                        <p className="text-xl text-slate-500 font-medium leading-relaxed max-w-lg mb-12">
                            {t.hero.description}
                        </p>

                        {/* Integrated Search Bar */}
                        <div className="relative group max-w-xl">
                            <div className="absolute inset-0 bg-indigo-600/10 blur-2xl group-hover:bg-indigo-600/20 transition-all rounded-3xl" />
                            <div className="relative flex p-2 bg-white border border-slate-200 rounded-3xl shadow-2xl shadow-slate-200/50">
                                <Search className="w-6 h-6 text-slate-400 ml-4 my-auto" />
                                <input
                                    type="text"
                                    placeholder={t.search.placeholder}
                                    className="flex-1 px-4 py-4 bg-transparent border-none focus:ring-0 font-bold text-slate-700"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                />
                                <button
                                    onClick={handleDetectLocation}
                                    className={`px-6 bg-slate-50 h-14 rounded-2xl flex items-center font-bold text-sm text-slate-600 hover:bg-slate-100 transition-all ${isDetecting ? 'animate-pulse' : ''}`}
                                >
                                    <MapPin className="w-4 h-4 mr-2 text-indigo-600" />
                                    {isDetecting ? t.search.detecting : t.search.location_btn}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="hidden lg:block relative animate-fade-up delay-100">
                        <div className="relative w-full aspect-square rounded-[3rem] overflow-hidden rotate-3 shadow-2xl border-8 border-white">
                            <img
                                src="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=80"
                                className="w-full h-full object-cover scale-110"
                                alt="Event Hero"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent" />
                        </div>
                        {/* Floating elements */}
                        <div className="absolute -bottom-10 -left-10 p-6 bg-white border border-slate-100 rounded-3xl shadow-2xl animate-bounce">
                            <CheckCircle2 className="text-green-500 w-12 h-12" />
                        </div>
                    </div>
                </div>
            </section>

            {/* Event Discovery Section */}
            <section id="events" className="py-24 bg-slate-50/50">
                <div className="max-w-7xl mx-auto px-6">
                    <div className="flex flex-col md:flex-row justify-between items-end mb-12 gap-8">
                        <div>
                            <h2 className="text-4xl font-black tracking-tight mb-4">
                                {userLocation ? t.search.nearby_title : t.search.trending_title}
                            </h2>
                            <p className="text-slate-500 font-bold uppercase tracking-widest text-[10px]">{t.hero.trusted_by}</p>
                        </div>

                        {/* Category Filters */}
                        <div className="flex flex-wrap gap-2">
                            <button
                                onClick={() => setSelectedCategory('all')}
                                className={`px-5 py-2.5 rounded-xl font-bold text-sm flex items-center transition-all ${selectedCategory === 'all' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-white border border-slate-200 text-slate-600 hover:border-indigo-300'}`}
                            >
                                <Filter className="w-4 h-4 mr-2" />
                                {t.search.category_all}
                            </button>
                            {categories.map((cat) => (
                                <button
                                    key={cat.id}
                                    onClick={() => setSelectedCategory(cat.id)}
                                    className={`px-5 py-2.5 rounded-xl font-bold text-sm flex items-center transition-all ${selectedCategory === cat.id ? 'bg-indigo-600 text-white shadow-lg' : 'bg-white border border-slate-200 text-slate-600 hover:border-indigo-300'}`}
                                >
                                    <cat.icon className="w-4 h-4 mr-2" />
                                    {cat.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Events Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                        {filteredEvents.length > 0 ? (
                            filteredEvents.map((event) => (
                                <div key={event.id} className="group bg-white rounded-[2.5rem] border border-slate-100 overflow-hidden hover:shadow-2xl hover:shadow-indigo-600/10 transition-all duration-300 cursor-pointer">
                                    <div className="relative h-64 overflow-hidden">
                                        <img src={event.image} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt={event.title} />
                                        <div className="absolute top-6 left-6 flex space-x-2">
                                            <div className="px-4 py-1.5 bg-white/90 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-widest text-indigo-600">
                                                {event.category}
                                            </div>
                                            <div className="px-4 py-1.5 bg-black/50 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-widest text-white">
                                                {event.price}
                                            </div>
                                        </div>
                                        <button className="absolute top-6 right-6 p-2.5 bg-white/20 hover:bg-white/90 backdrop-blur-md rounded-2xl text-white hover:text-rose-500 transition-all">
                                            <Heart className="w-5 h-5" />
                                        </button>
                                    </div>
                                    <div className="p-8">
                                        <div className="flex items-center text-slate-400 text-xs font-bold mb-4 space-x-4">
                                            <div className="flex items-center"><Calendar size={14} className="mr-1.5" /> {event.date}</div>
                                            <div className="flex items-center"><MapPin size={14} className="mr-1.5" /> {event.location}</div>
                                        </div>
                                        <h3 className="text-2xl font-black mb-6 group-hover:text-indigo-600 transition-colors leading-tight">{event.title}</h3>
                                        <div className="pt-6 border-t border-slate-50 flex justify-between items-center">
                                            <div className="flex -space-x-2">
                                                {[1, 2, 3].map(i => (
                                                    <div key={i} className="w-8 h-8 rounded-full border-2 border-white bg-slate-200 overflow-hidden">
                                                        <img src={`https://i.pravatar.cc/100?u=${event.id + i}`} alt="Attendee" />
                                                    </div>
                                                ))}
                                                <div className="w-8 h-8 rounded-full border-2 border-white bg-indigo-50 flex items-center justify-center text-[10px] font-black text-indigo-600">+12</div>
                                            </div>
                                            <button className="p-3 bg-slate-50 rounded-2xl group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                                <ArrowRight size={20} />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="col-span-full py-20 text-center">
                                <div className="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-400">
                                    <Search size={32} />
                                </div>
                                <h3 className="text-xl font-black text-slate-400">{t.search.no_results}</h3>
                            </div>
                        )}
                    </div>
                </div>
            </section>

            {/* Multi-language Cta */}
            <section className="py-24 overflow-hidden relative">
                <div className="max-w-7xl mx-auto px-6">
                    <div className="bg-indigo-600 rounded-[3rem] p-12 lg:p-24 relative overflow-hidden flex flex-col items-center text-center">
                        <div className="absolute top-0 right-0 w-64 h-64 bg-white/10 blur-[80px] -translate-y-1/2 translate-x-1/2" />
                        <h2 className="text-4xl lg:text-7xl font-black text-white leading-none mb-4">{t.hero.cta_primary}</h2>
                        <h2 className="text-4xl lg:text-7xl font-black text-indigo-400 opacity-30 leading-none mb-12">SEKARANG • NOW</h2>

                        <div className="flex flex-col sm:flex-row gap-4">
                            <Link href={route('login')} className="px-12 py-5 bg-white text-indigo-600 rounded-3xl font-black text-xl shadow-2xl hover:scale-105 transition-all">
                                {t.nav.login}
                            </Link>
                            <button className="px-12 py-5 border-2 border-white/30 text-white rounded-3xl font-black text-xl hover:bg-white/10 transition-all">
                                {t.hero.cta_secondary}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="py-20 border-t border-slate-100">
                <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-16">
                    <div className="col-span-1 lg:col-span-2 space-y-8">
                        <div className="flex items-center">
                            <div className="w-8 h-8 bg-indigo-600 rounded-xl flex items-center justify-center text-white">
                                <Sparkles size={16} />
                            </div>
                            <span className="ml-3 text-xl font-black tracking-tighter">EVENTO</span>
                        </div>
                        <p className="text-slate-400 font-medium max-w-sm">{t.footer.tagline}</p>
                    </div>

                    <div className="space-y-6">
                        <h4 className="font-black uppercase tracking-widest text-xs text-slate-400">{t.nav.features}</h4>
                        <ul className="space-y-4 font-bold text-slate-600">
                            <li><a href="#" className="hover:text-indigo-600">Scan QR</a></li>
                            <li><a href="#" className="hover:text-indigo-600">WhatsApp Auth</a></li>
                            <li><a href="#" className="hover:text-indigo-600">Analytics</a></li>
                        </ul>
                    </div>

                    <div className="space-y-6">
                        <h4 className="font-black uppercase tracking-widest text-xs text-slate-400">Support</h4>
                        <ul className="space-y-4 font-bold text-slate-600">
                            <li><a href="#" className="hover:text-indigo-600">Help Center</a></li>
                            <li><a href="#" className="hover:text-indigo-600">Contact Us</a></li>
                            <li><a href="#" className="hover:text-indigo-600">Privacy Policy</a></li>
                        </ul>
                    </div>
                </div>
                <div className="max-w-7xl mx-auto px-6 mt-20 pt-10 border-t border-slate-50 text-center">
                    <p className="text-[10px] font-black text-slate-300 uppercase tracking-[0.3em]">{t.footer.rights}</p>
                </div>
            </footer>
        </div>
    );
}
