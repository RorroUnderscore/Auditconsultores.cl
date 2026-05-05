<?php declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dbPath = __DIR__ . '/../data/app.sqlite';
    if (!is_dir(dirname($dbPath))) mkdir(dirname($dbPath), 0775, true);
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, password_hash TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS institutions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS projects (id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER NOT NULL, name TEXT NOT NULL, FOREIGN KEY(institution_id) REFERENCES institutions(id))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS surveys (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, name TEXT NOT NULL, FOREIGN KEY(project_id) REFERENCES projects(id))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS forms (id INTEGER PRIMARY KEY AUTOINCREMENT, survey_id INTEGER NOT NULL, estate TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft")');
    $pdo->exec('CREATE TABLE IF NOT EXISTS questions (id INTEGER PRIMARY KEY AUTOINCREMENT, form_id INTEGER NOT NULL, text TEXT NOT NULL, q_order INTEGER NOT NULL DEFAULT 1, required INTEGER NOT NULL DEFAULT 1)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS participants (id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER NOT NULL, project_id INTEGER NOT NULL, estate TEXT NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS invitation_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, participant_id INTEGER NOT NULL, form_id INTEGER NOT NULL, token TEXT UNIQUE NOT NULL, used_at TEXT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS responses (id INTEGER PRIMARY KEY AUTOINCREMENT, token_id INTEGER NOT NULL, submitted_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS response_answers (id INTEGER PRIMARY KEY AUTOINCREMENT, response_id INTEGER NOT NULL, question_id INTEGER NOT NULL, value INTEGER NOT NULL)');

    $count = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count === 0) {
      $stmt = $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES (?,?)');
      $stmt->execute(['admin@auditconsultores.cl', password_hash('admin1234', PASSWORD_DEFAULT)]);
    }
}
