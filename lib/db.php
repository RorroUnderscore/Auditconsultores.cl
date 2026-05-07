<?php declare(strict_types=1);

function appConfig(): array {
    static $cfg = null;
    if (is_array($cfg)) return $cfg;
    $file = __DIR__ . '/../config/app.php';
    $cfg = is_file($file) ? require $file : [];
    return is_array($cfg) ? $cfg : [];
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $cfg = appConfig();
    $dbCfg = $cfg['db'] ?? [];

    $driver = $dbCfg['connection'] ?? (getenv('DB_CONNECTION') ?: 'sqlite');

    if ($driver === 'mysql') {
        $host = $dbCfg['host'] ?? (getenv('DB_HOST') ?: 'localhost');
        $port = (string)($dbCfg['port'] ?? (getenv('DB_PORT') ?: '3306'));
        $name = $dbCfg['database'] ?? (getenv('DB_DATABASE') ?: '');
        $user = $dbCfg['username'] ?? (getenv('DB_USERNAME') ?: '');
        $pass = $dbCfg['password'] ?? (getenv('DB_PASSWORD') ?: '');
        if ($name === '' || $user === '') {
            throw new RuntimeException('Faltan DB_DATABASE o DB_USERNAME para conexión MySQL.');
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        try {
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        } catch (Throwable $e) {
            error_log('[DB_MYSQL_FALLBACK] ' . $e->getMessage());
            $driver = 'sqlite';
        }
    }

    if ($driver !== 'mysql') {
        $dbPath = __DIR__ . '/../data/app.sqlite';
        if (!is_dir(dirname($dbPath))) mkdir(dirname($dbPath), 0775, true);
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    migrate($pdo);
    return $pdo;
}


function safeExec(PDO $pdo, string $sql): void {
    try { $pdo->exec($sql); } catch (Throwable $e) { /* noop para compatibilidad */ }
}

function migrate(PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $idCol = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text = $driver === 'mysql' ? 'VARCHAR(255)' : 'TEXT';
    $longText = $driver === 'mysql' ? 'TEXT' : 'TEXT';

    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id $idCol, email $text UNIQUE, password_hash $text NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS institutions (id $idCol, name $text NOT NULL)");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN code $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN rbd $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN dependency $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN region $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN commune $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN address_line $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN phone $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN email $text NULL");
    safeExec($pdo, "ALTER TABLE institutions ADD COLUMN status $text NOT NULL DEFAULT 'active'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS institution_contacts (id $idCol, institution_id INT NOT NULL, full_name $text NOT NULL, role_title $text NULL, email $text NULL, phone $text NULL, is_primary TINYINT NOT NULL DEFAULT 0, FOREIGN KEY(institution_id) REFERENCES institutions(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (id $idCol, institution_id INT NOT NULL, name $text NOT NULL, FOREIGN KEY(institution_id) REFERENCES institutions(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (id $idCol, project_id INT NOT NULL, name $text NOT NULL, FOREIGN KEY(project_id) REFERENCES projects(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS forms (id $idCol, survey_id INT NOT NULL, estate $text NOT NULL, status $text NOT NULL DEFAULT 'draft', FOREIGN KEY(survey_id) REFERENCES surveys(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (id $idCol, form_id INT NOT NULL, text $longText NOT NULL, q_order INT NOT NULL DEFAULT 1, required TINYINT NOT NULL DEFAULT 1, FOREIGN KEY(form_id) REFERENCES forms(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS participants (id $idCol, institution_id INT NOT NULL, project_id INT NOT NULL, estate $text NOT NULL, name $text NOT NULL, email $text NOT NULL, FOREIGN KEY(institution_id) REFERENCES institutions(id), FOREIGN KEY(project_id) REFERENCES projects(id))");
    safeExec($pdo, "ALTER TABLE participants ADD COLUMN last_name $text NULL");
    safeExec($pdo, "ALTER TABLE participants ADD COLUMN responded_at $text NULL");
    safeExec($pdo, "ALTER TABLE participants ADD COLUMN email_delivery_status $text NOT NULL DEFAULT 'pending'");
    safeExec($pdo, "ALTER TABLE participants ADD COLUMN email_sent_at $text NULL");
    safeExec($pdo, "ALTER TABLE participants ADD COLUMN reminder_sent_at $text NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS communication_templates (id $idCol, institution_id INT NOT NULL, template_type $text NOT NULL, subject $text NOT NULL, body $longText NOT NULL, updated_at $text NOT NULL, is_approved TINYINT NOT NULL DEFAULT 0, approved_at $text NULL, UNIQUE(institution_id, template_type), FOREIGN KEY(institution_id) REFERENCES institutions(id) ON DELETE CASCADE)");
    safeExec($pdo, "ALTER TABLE communication_templates ADD COLUMN is_approved TINYINT NOT NULL DEFAULT 0");
    safeExec($pdo, "ALTER TABLE communication_templates ADD COLUMN approved_at $text NULL");
$pdo->exec("CREATE TABLE IF NOT EXISTS invitation_tokens (id $idCol, participant_id INT NOT NULL, form_id INT NOT NULL, token $text UNIQUE NOT NULL, used_at $text NULL, FOREIGN KEY(participant_id) REFERENCES participants(id), FOREIGN KEY(form_id) REFERENCES forms(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS responses (id $idCol, token_id INT NOT NULL, submitted_at $text NOT NULL, FOREIGN KEY(token_id) REFERENCES invitation_tokens(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS response_answers (id $idCol, response_id INT NOT NULL, question_id INT NOT NULL, value INT NOT NULL, FOREIGN KEY(response_id) REFERENCES responses(id), FOREIGN KEY(question_id) REFERENCES questions(id))");

    seedDefaultAdmin($pdo);
}

function seedDefaultAdmin(PDO $pdo): void {
    $cfg = appConfig();
    $seedCfg = $cfg['admin_seed'] ?? [];
    $adminEmail = $seedCfg['email'] ?? (getenv('ADMIN_DEFAULT_EMAIL') ?: 'admin@auditconsultores.cl');
    $adminPassword = $seedCfg['password'] ?? (getenv('ADMIN_DEFAULT_PASSWORD') ?: 'admin1234');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
    $stmt->execute([$adminEmail]);
    if ((int)$stmt->fetchColumn() > 0) return;

    $stmt = $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES (?,?)');
    $stmt->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
}
