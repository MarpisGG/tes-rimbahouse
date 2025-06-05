# Task Management Laravel API

## Deskripsi
Aplikasi manajemen tugas (Task Management) dengan fitur CRUD, role & permission, dan activity log. Backend menggunakan Laravel.

---

## Fitur
- Autentikasi dengan Laravel Sanctum 
- Role & Permission dengan Spatie Laravel Permission
- CRUD Task (Create, Read, Update, Delete)
- Task hanya bisa dilihat oleh user yang ditugaskan
- Activity Log untuk mencatat aksi pengguna

---

## ERD (Entity Relationship Diagram)
![image](https://github.com/user-attachments/assets/a0d20fe1-870b-4cc7-b90b-c1a4f1d69a9b)


#### Setup Project

### 1. Clone repository
git clone https://github.com/username/tes-rimbahouse.git
cd tes-rimbahouse

### 2. Install Dependencies
composer install
npm install
npm run dev

### 3. Copy file .env.example ke .env dan sesuaikan konfigurasi database dan lainnya
cp .env.example .env

### 4. Generate app key
php artisan key:generate

### 5. Migrasi database dan seed
php artisan migrate --seed

### 6. Jalankan server Laravel
php artisan serve


#### Screenshot
### 1. Login
![image](https://github.com/user-attachments/assets/be4cbdc1-42a2-4f8c-b754-7ed3db080722)
### 2. CRUD Task - Create
![image](https://github.com/user-attachments/assets/6e774eae-fc48-4bf3-ad21-9de611f55ecf)
### 3. CRUD Task - List
![image](https://github.com/user-attachments/assets/0338ce5d-5490-49f8-96ea-df4c4a389a84)
