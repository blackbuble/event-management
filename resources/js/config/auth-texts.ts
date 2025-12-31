export const authTexts = {
    id: {
        login: {
            title: 'Masuk atau Daftar',
            subtitle: 'Gunakan akun Google, Email, atau WhatsApp Anda.',
            identity_label: 'Email atau No. WhatsApp',
            identity_placeholder: 'contoh@email.com atau 0812...',
            submit_magic: 'Kirim Tautan Login',
            submit_otp: 'Kirim Kode Verifikasi',
            processing: 'Memproses...',
            auto_account_info: 'Jika belum punya akun, kami akan otomatis membuatkan akun baru untuk Anda.',
            tagline: 'Cepat • Aman • Tanpa Password',
            or_divider: 'Atau Masukkan Identitas',
        },
        otp: {
            title: 'Verifikasi Akun',
            subtitle_email: 'Kami telah mengirimkan tautan login ke email Anda.',
            subtitle_phone: 'Kami telah mengirimkan kode 6-digit ke WhatsApp Anda.',
            submit_button: 'Verifikasi & Masuk',
            resend_text: 'Tidak menerima kode?',
            resend_link: 'Kirim ulang',
            resend_wait: 'Kirim Ulang dalam {timer}s',
            change_identity: 'Ganti Identitas',
            invalid_otp: 'Terlalu banyak percobaan salah. Silakan minta kode baru.',
            otp_remaining: 'Kode OTP salah. Sisa percobaan: {count}',
        },
        common: {
            error_title: 'Terjadi Kesalahan',
            success_title: 'Berhasil',
        }
    },
    en: {
        login: {
            title: 'Login or Register',
            subtitle: 'Use your Google, Email, or WhatsApp account.',
            identity_label: 'Email or WhatsApp No.',
            identity_placeholder: 'example@email.com or 0812...',
            submit_magic: 'Send Login Link',
            submit_otp: 'Send Verification Code',
            processing: 'Processing...',
            auto_account_info: 'If you don\'t have an account, we\'ll automatically create a new one for you.',
            tagline: 'Fast • Secure • Passwordless',
            or_divider: 'Or Enter Identity',
        },
        otp: {
            title: 'Verify Account',
            subtitle_email: 'We have sent a login link to your email.',
            subtitle_phone: 'We have sent a 6-digit code to your WhatsApp.',
            submit_button: 'Verify & Login',
            resend_text: 'Didn\'t receive the code?',
            resend_link: 'Resend',
            resend_wait: 'Resend in {timer}s',
            change_identity: 'Change Identity',
            invalid_otp: 'Too many wrong attempts. Please request a new code.',
            otp_remaining: 'Wrong OTP code. Remaining attempts: {count}',
        },
        common: {
            error_title: 'An Error Occurred',
            success_title: 'Success',
        }
    }
};

export type Language = 'id' | 'en';
export const defaultLanguage: Language = 'id';
