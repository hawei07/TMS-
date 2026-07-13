<?php

declare(strict_types=1);

final class MigrationRunner
{
    private const LOCK_NAME = 'tms_schema_migrations';
    private const LOCK_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly PDO $db,
        private readonly string $migrationDirectory
    ) {
    }

    /**
     * @return list<string>
     */
    public function migrate(bool $force = false, ?callable $logger = null): array
    {
        $migrations = $this->loadMigrations();
        $appliedVersions = $this->loadAppliedVersions();
        $pending = $this->pendingMigrations($migrations, $appliedVersions, $force);

        if ($pending === []) {
            return [];
        }

        $this->acquireLock();
        try {
            $appliedVersions = $this->loadAppliedVersions();
            $pending = $this->pendingMigrations($migrations, $appliedVersions, $force);
            $executed = [];

            foreach ($pending as $migration) {
                $version = $migration['version'];
                if ($logger !== null) {
                    $logger(sprintf('Applying %s: %s', $version, $migration['description']));
                }
                $migration['up']($this->db);
                $this->recordMigration($version, $migration['description']);
                $executed[] = $version;
                if ($logger !== null) {
                    $logger(sprintf('Applied %s', $version));
                }
            }

            return $executed;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return list<array{version:string,description:string,applied:bool,applied_at:?string}>
     */
    public function status(): array
    {
        $migrations = $this->loadMigrations();
        $applied = $this->loadAppliedVersions();
        $status = [];

        foreach ($migrations as $version => $migration) {
            $status[] = [
                'version' => $version,
                'description' => $migration['description'],
                'applied' => isset($applied[$version]),
                'applied_at' => $applied[$version] ?? null,
            ];
        }

        return $status;
    }

    /**
     * @return array<string,array{version:string,description:string,up:Closure}>
     */
    private function loadMigrations(): array
    {
        $files = glob($this->migrationDirectory . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $migrations = [];

        foreach ($files as $file) {
            $definition = require $file;
            if (!is_array($definition)) {
                throw new RuntimeException("Migration file must return an array: {$file}");
            }

            $version = (string)($definition['version'] ?? '');
            $description = (string)($definition['description'] ?? '');
            $up = $definition['up'] ?? null;

            if ($version === '' || !preg_match('/^[0-9]{8}_[0-9]{3}_[a-z0-9_]+$/', $version)) {
                throw new RuntimeException("Invalid migration version in {$file}");
            }
            if ($description === '' || !is_callable($up)) {
                throw new RuntimeException("Migration description or up callback missing in {$file}");
            }
            if (isset($migrations[$version])) {
                throw new RuntimeException("Duplicate migration version: {$version}");
            }

            $migrations[$version] = [
                'version' => $version,
                'description' => $description,
                'up' => Closure::fromCallable($up),
            ];
        }

        ksort($migrations, SORT_STRING);
        return $migrations;
    }

    /**
     * @return array<string,string>
     */
    private function loadAppliedVersions(): array
    {
        try {
            $rows = $this->db
                ->query('SELECT version, applied_at FROM schema_migrations ORDER BY version')
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (!$this->isMissingTableError($e)) {
                throw $e;
            }

            $this->createMigrationTable();
            return [];
        }

        $applied = [];
        foreach ($rows as $row) {
            $applied[(string)$row['version']] = (string)$row['applied_at'];
        }

        return $applied;
    }

    private function createMigrationTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(100) PRIMARY KEY,
                description VARCHAR(255) NOT NULL DEFAULT '',
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function isMissingTableError(PDOException $e): bool
    {
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return $e->getCode() === '42S02' || $driverCode === 1146;
    }

    /**
     * @param array<string,array{version:string,description:string,up:Closure}> $migrations
     * @param array<string,string> $appliedVersions
     * @return list<array{version:string,description:string,up:Closure}>
     */
    private function pendingMigrations(array $migrations, array $appliedVersions, bool $force): array
    {
        return array_values(array_filter(
            $migrations,
            static fn(array $migration): bool => $force || !isset($appliedVersions[$migration['version']])
        ));
    }

    private function recordMigration(string $version, string $description): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO schema_migrations (version, description, applied_at)
             VALUES (:version, :description, NOW())
             ON DUPLICATE KEY UPDATE description = VALUES(description), applied_at = VALUES(applied_at)'
        );
        $stmt->execute([
            ':version' => $version,
            ':description' => $description,
        ]);
    }

    private function acquireLock(): void
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(:lock_name, :timeout_seconds)');
        $stmt->bindValue(':lock_name', self::LOCK_NAME, PDO::PARAM_STR);
        $stmt->bindValue(':timeout_seconds', self::LOCK_TIMEOUT_SECONDS, PDO::PARAM_INT);
        $stmt->execute();

        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Could not acquire the schema migration lock.');
        }
    }

    private function releaseLock(): void
    {
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $stmt->execute([':lock_name' => self::LOCK_NAME]);
        } catch (Throwable $e) {
            error_log('Failed to release schema migration lock: ' . $e->getMessage());
        }
    }
}
