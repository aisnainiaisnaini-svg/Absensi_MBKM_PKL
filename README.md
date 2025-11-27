# Aplikasi Pengawasan dan Pengelolaan Kegiatan Magang dan PKL

Aplikasi web untuk mengelola dan memantau kegiatan magang dan PKL di lingkungan perusahaan dengan fitur lengkap untuk peserta, pembimbing, dan admin.

## 🚀 Fitur Utama

### 1. Sistem Login Multi-Role
- **Peserta**: Akses untuk mengisi absensi, mengajukan izin, dan membuat laporan
- **Pembimbing**: Mengawasi peserta, menyetujui izin, dan memberikan feedback
- **Admin**: Mengelola seluruh sistem, user, dan data perusahaan

### 2. Dashboard Interaktif
- Ringkasan data kehadiran dan laporan
- Statistik real-time berdasarkan role pengguna
- Tampilan yang responsif dan modern

### 3. Sistem Absensi Digital
- Check-in dan check-out online
- Riwayat kehadiran lengkap dengan statistik
- Perhitungan jam kerja otomatis

### 4. Manajemen Izin
- Pengajuan izin online (sakit, izin pribadi, izin akademik)
- Persetujuan izin oleh pembimbing
- Tracking status pengajuan

### 5. Laporan Kegiatan
- Upload laporan harian/mingguan
- Upload file pendukung (PDF, DOC, JPG, dll)
- Rating dan komentar dari pembimbing

### 6. Panel Admin
- Kelola user dan peserta
- Kelola divisi perusahaan
- Laporan sistem lengkap

## 🛠️ Teknologi yang Digunakan

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: Bootstrap 5, HTML5, CSS3, JavaScript
- **Icons**: Font Awesome 6
- **Security**: Password hashing, SQL injection prevention

## 📋 Persyaratan Sistem

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web server (Apache/Nginx)
- Browser modern (Chrome, Firefox, Safari, Edge)

## 🔧 Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/username/aplikasi-pengawasan-magang.git
cd aplikasi-pengawasan-magang
```

### 2. Setup Database
1. Buat database MySQL baru:
```sql
CREATE DATABASE aplikasi_pengawasan_magang;
```

2. Import schema database:
```bash
mysql -u username -p aplikasi_pengawasan_magang < database/schema.sql
```

### 3. Konfigurasi Database
Edit file `config/database.php`:
```php
$host = 'localhost';
$dbname = 'db_Pengawas';
$username = 'Ais';
$password = '123';
```

### 4. Setup Web Server
- Copy semua file ke direktori web server
- Pastikan folder `uploads/` memiliki permission write
- Akses aplikasi melalui browser

## 👥 Akun Default

### Admin
- **Username**: admin
- **Password**: password

### Pembimbing
- **Username**: pembimbing1
- **Password**: password

## 📁 Struktur File

```
aplikasi-pengawasan-magang/
├── config/
│   └── database.php          # Konfigurasi database
├── database/
│   └── schema.sql            # Schema database
├── admin/
│   ├── users.php             # Kelola user
│   ├── participants.php      # Kelola peserta
│   ├── divisions.php         # Kelola divisi
│   └── reports.php           # Laporan sistem
├── uploads/
│   └── reports/              # Folder upload laporan
├── index.php                 # Halaman login
├── dashboard.php             # Dashboard utama
├── attendance.php            # Absensi harian
├── attendance_history.php   # Riwayat kehadiran
├── leave_request.php         # Pengajuan izin
├── activity_report.php       # Laporan kegiatan
└── logout.php                # Logout
```

## 🎯 Cara Penggunaan

### Untuk Peserta
1. Login dengan akun peserta
2. Lakukan check-in setiap hari kerja
3. Ajukan izin jika diperlukan
4. Buat laporan kegiatan harian/mingguan
5. Lihat riwayat kehadiran dan statistik

### Untuk Pembimbing
1. Login dengan akun pembimbing
2. Lihat daftar peserta yang dibimbing
3. Setujui/tolak pengajuan izin
4. Review dan berikan rating laporan peserta
5. Berikan komentar dan feedback

### Untuk Admin
1. Login dengan akun admin
2. Kelola user dan peserta
3. Kelola divisi perusahaan
4. Lihat laporan sistem keseluruhan
5. Monitor aktivitas semua pengguna

## 🔒 Keamanan

- Password di-hash menggunakan PHP password_hash()
- Prepared statements untuk mencegah SQL injection
- Session management untuk autentikasi
- Validasi input pada semua form
- File upload dengan validasi ekstensi

## 📊 Database Schema

### Tabel Utama
- **users**: Data pengguna sistem
- **participants**: Data peserta magang/PKL
- **divisions**: Data divisi perusahaan
- **attendance**: Data kehadiran harian
- **leave_requests**: Data pengajuan izin
- **activity_reports**: Data laporan kegiatan
- **schedules**: Jadwal kerja divisi

## 🚀 Fitur Mendatang

- [ ] Notifikasi email otomatis
- [ ] Export laporan ke PDF/Excel
- [ ] Mobile app (React Native)
- [ ] API untuk integrasi eksternal
- [ ] Dashboard analytics yang lebih detail
- [ ] Sistem backup otomatis

## 🤝 Kontribusi

1. Fork repository ini
2. Buat branch fitur baru (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## 📝 Lisensi

Distributed under the MIT License. See `LICENSE` for more information.

## 📞 Kontak

- **Developer**: [Nama Developer]
- **Email**: developer@company.com
- **Project Link**: [https://github.com/username/aplikasi-pengawasan-magang](https://github.com/username/aplikasi-pengawasan-magang)

## 🙏 Acknowledgments

- Bootstrap untuk UI framework
- Font Awesome untuk icons
- PHP community untuk dokumentasi
- Semua kontributor yang telah membantu pengembangan

---

**Aplikasi Pengawasan Magang/PKL** - Solusi digital untuk manajemen magang dan PKL yang efisien dan terintegrasi.
