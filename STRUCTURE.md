# Struktur Aplikasi Pengawasan Magang/PKL

## 📁 Struktur File Lengkap

```
aplikasi-pengawasan-magang/
├── 📁 config/                          # Konfigurasi aplikasi
│   ├── database.php                    # Konfigurasi database
│   └── app.php                         # Konfigurasi aplikasi
├── 📁 database/                        # Database schema
│   └── schema.sql                      # Schema database MySQL
├── 📁 admin/                           # Panel admin
│   ├── users.php                       # Kelola user
│   ├── participants.php                # Kelola peserta
│   ├── divisions.php                   # Kelola divisi
│   └── reports.php                     # Laporan sistem
├── 📁 uploads/                         # Folder upload file
│   └── reports/                        # Upload laporan peserta
├── 🔐 .htaccess                        # Konfigurasi server
├── 📄 index.php                        # Halaman login
├── 📄 dashboard.php                    # Dashboard utama
├── 📄 attendance.php                   # Absensi harian
├── 📄 attendance_history.php           # Riwayat kehadiran
├── 📄 leave_request.php                # Pengajuan izin
├── 📄 leave_approval.php               # Persetujuan izin (pembimbing)
├── 📄 activity_report.php              # Laporan kegiatan
├── 📄 reports_review.php               # Review laporan (pembimbing)
├── 📄 participants.php                 # Data peserta (pembimbing)
├── 📄 participant_detail.php           # Detail peserta
├── 📄 logout.php                       # Logout
├── 📄 install.php                      # File instalasi
├── 📄 README.md                        # Dokumentasi
└── 📄 STRUCTURE.md                     # Struktur aplikasi
```

## 🎯 Fitur per Role

### 👤 Peserta
- ✅ Login dengan akun peserta
- ✅ Dashboard dengan statistik pribadi
- ✅ Absensi harian (check-in/check-out)
- ✅ Riwayat kehadiran dengan filter bulan
- ✅ Pengajuan izin (sakit, izin pribadi, izin akademik)
- ✅ Laporan kegiatan dengan upload file
- ✅ Detail profil dan statistik

### 👨‍🏫 Pembimbing
- ✅ Login dengan akun pembimbing
- ✅ Dashboard dengan data peserta bimbingan
- ✅ Data peserta yang dibimbing
- ✅ Persetujuan izin peserta
- ✅ Review dan rating laporan peserta
- ✅ Detail peserta dengan statistik lengkap

### 👨‍💼 Admin
- ✅ Login dengan akun admin
- ✅ Dashboard dengan statistik sistem
- ✅ Kelola user (tambah, edit, hapus)
- ✅ Kelola peserta (tambah, edit status)
- ✅ Kelola divisi perusahaan
- ✅ Laporan sistem lengkap dengan analisis

## 🗄️ Database Schema

### Tabel Utama
1. **users** - Data pengguna sistem
2. **participants** - Data peserta magang/PKL
3. **divisions** - Data divisi perusahaan
4. **attendance** - Data kehadiran harian
5. **leave_requests** - Data pengajuan izin
6. **activity_reports** - Data laporan kegiatan
7. **schedules** - Jadwal kerja divisi

### Relasi Database
- `participants.user_id` → `users.id`
- `participants.division_id` → `divisions.id`
- `participants.supervisor_id` → `users.id`
- `attendance.participant_id` → `participants.id`
- `leave_requests.participant_id` → `participants.id`
- `activity_reports.participant_id` → `participants.id`

## 🚀 Cara Instalasi

### 1. Setup Database
```sql
CREATE DATABASE aplikasi_pengawasan_magang;
```

### 2. Import Schema
```bash
mysql -u username -p aplikasi_pengawasan_magang < database/schema.sql
```

### 3. Konfigurasi
Edit `config/database.php`:
```php
$host = 'localhost';
$dbname = 'db_Pengawas';
$username = 'Ais';
$password = '123';
```

### 4. Jalankan Instalasi
Akses `install.php` di browser untuk setup otomatis.

### 5. Login Default
- **Admin:** admin / password
- **Pembimbing:** pembimbing1 / password

## 🔧 Teknologi yang Digunakan

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Frontend:** Bootstrap 5, HTML5, CSS3, JavaScript
- **Icons:** Font Awesome 6
- **Security:** Password hashing, SQL injection prevention

## 📊 Fitur Unggulan

### 🎨 UI/UX Modern
- Desain responsif dengan Bootstrap 5
- Gradient background yang menarik
- Card-based layout
- Interactive elements dengan hover effects

### 🔒 Keamanan Tinggi
- Password hashing dengan PHP password_hash()
- Prepared statements untuk mencegah SQL injection
- Session management untuk autentikasi
- Validasi input pada semua form
- File upload dengan validasi ekstensi

### 📱 Mobile Friendly
- Responsive design untuk semua device
- Touch-friendly interface
- Optimized untuk mobile browser

### 📈 Analytics & Reporting
- Dashboard dengan statistik real-time
- Laporan sistem lengkap untuk admin
- Filter data berdasarkan periode
- Export data (dapat dikembangkan)

## 🎯 Workflow Aplikasi

### Untuk Peserta:
1. Login → Dashboard → Absensi Harian
2. Ajukan Izin → Tunggu Persetujuan
3. Buat Laporan → Upload File → Tunggu Review
4. Lihat Riwayat → Statistik Pribadi

### Untuk Pembimbing:
1. Login → Dashboard → Lihat Peserta
2. Review Izin → Setujui/Tolak
3. Review Laporan → Berikan Rating
4. Monitor Performa Peserta

### Untuk Admin:
1. Login → Dashboard → Kelola Sistem
2. Kelola User → Kelola Peserta → Kelola Divisi
3. Lihat Laporan Sistem → Analisis Data

## 🔮 Pengembangan Selanjutnya

- [ ] Notifikasi email otomatis
- [ ] Export laporan ke PDF/Excel
- [ ] Mobile app (React Native)
- [ ] API untuk integrasi eksternal
- [ ] Dashboard analytics yang lebih detail
- [ ] Sistem backup otomatis
- [ ] Multi-language support
- [ ] Advanced reporting dengan chart

## 📞 Support

Untuk pertanyaan atau bantuan teknis, silakan hubungi developer atau buat issue di repository.

---

**Aplikasi Pengawasan Magang/PKL** - Solusi digital lengkap untuk manajemen magang dan PKL yang efisien dan terintegrasi! 🚀
