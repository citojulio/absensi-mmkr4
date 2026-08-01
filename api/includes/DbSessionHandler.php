<?php
/**
 * Session handler berbasis database.
 *
 * KENAPA INI PERLU? Di hosting PHP biasa, session disimpan sebagai file kecil
 * di disk server, dan disk itu selalu sama untuk setiap request selanjutnya.
 *
 * Di Vercel (serverless), setiap request BISA dilayani oleh instance/container
 * yang berbeda dan disknya tidak dijamin persisten antar request. Kalau kita
 * tetap pakai session file bawaan PHP, admin bisa saja "ke-logout sendiri"
 * secara acak karena request berikutnya dilayani instance lain yang tidak
 * punya file session tadi.
 *
 * Solusinya: simpan data session di tabel MySQL (yang memang sudah terpusat
 * dan diakses bersama oleh semua instance), bukan di file lokal.
 */
class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, data, last_activity) VALUES (:id, :data, :time)
             ON DUPLICATE KEY UPDATE data = :data2, last_activity = :time2'
        );
        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'time' => time(),
            'data2' => $data,
            'time2' => time(),
        ]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_activity < :expire');
        $stmt->execute(['expire' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
