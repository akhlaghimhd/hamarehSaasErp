<?php
declare(strict_types=1);
/**
 * Restore ADR-ID-ORG-005 remaining ceiling files onto local tree.
 * Run from repo root AFTER git pull origin develop:
 *   php database/scripts/ceiling/restore_ceiling_complete.php
 * Then commit + push the restored PHP sources.
 */
$root = getcwd();
if (!is_dir($root . '/app/Base/Services')) {
    fwrite(STDERR, "Run from hamarehSaasErp root\n");
    exit(1);
}

function download(string $url): string
{
    $ctx = stream_context_create(['http' => ['timeout' => 45, 'header' => "User-Agent: ceiling-restore\r\n"]]);
    $bin = @file_get_contents($url, false, $ctx);
    if ($bin === false || strlen($bin) < 1000) {
        throw new RuntimeException("download failed: $url");
    }
    return $bin;
}

// --- HAS L04 ---
$hasUrl = 'https://raw.githubusercontent.com/akhlaghimhd/hamarehSaasErp/c5eabb9705133ecb8862b3886984ea63bb07eca9/app/Base/Services/HoldingAccessService.php';
$has = download($hasUrl);
$old = <<<'OLD'
        if (!$exists) {
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت یا شعبه شما مجاز نیست.');
        }
    }

    public function assertCanManageUserId(string $userId): void
OLD;
$new = <<<'NEW'
        if (!$exists) {
            throw new RuntimeException('دسترسی به این عضو در محدوده شرکت یا شعبه شما مجاز نیست.');
        }

        // ADR-ID-ORG-005 L04 — when home_company_id is set, it must sit inside actor ceiling
        $this->assertTargetHomeWithinCeiling($tenantUserId);
    }

    public function assertCanManageUserId(string $userId): void
NEW;
if (strpos($has, 'assertTargetHomeWithinCeiling($tenantUserId)') === false) {
    if (strpos($has, $old) === false) {
        throw new RuntimeException('HAS patch pattern missing');
    }
    $has = str_replace($old, $new, $has);
}
file_put_contents($root . '/app/Base/Services/HoldingAccessService.php', $has);
echo 'OK HAS bytes=' . strlen($has) . PHP_EOL;

// --- ScopeService filter ---
$scopeUrl = 'https://raw.githubusercontent.com/akhlaghimhd/hamarehSaasErp/c5eabb9705133ecb8862b3886984ea63bb07eca9/app/Modules/IdentityCore/Services/ScopeService.php';
$scope = download($scopeUrl);
if (strpos($scope, 'filterScopesByActorCeiling') === false) {
    $sold = <<<'OLD'
        $scopes = $query->get()->all();
        $scopes = $this->hydrateReferenceIds($scopes);

        return array_map(static function (TenantScope $s): array {
            $row = $s->toArray();
            $refs = $s->getAttribute('reference_ids');
            $row['reference_ids'] = is_array($refs) ? array_values($refs) : [];
            if (empty($row['reference_ids']) && !empty($row['reference_id'])) {
                $row['reference_ids'] = [(string) $row['reference_id']];
            }

            return $row;
        }, $scopes);
    }
OLD;
    $snew = <<<'NEW'
        $scopes = $query->get()->all();
        $scopes = $this->hydrateReferenceIds($scopes);
        $scopes = $this->filterScopesByActorCeiling($scopes);

        return array_map(static function (TenantScope $s): array {
            $row = $s->toArray();
            $refs = $s->getAttribute('reference_ids');
            $row['reference_ids'] = is_array($refs) ? array_values($refs) : [];
            if (empty($row['reference_ids']) && !empty($row['reference_id'])) {
                $row['reference_ids'] = [(string) $row['reference_id']];
            }

            return $row;
        }, $scopes);
    }

    /**
     * ADR-ID-ORG-005 L02/L03 — company operators only see COMPANY/BRANCH scopes inside hierarchical ceiling.
     *
     * @param  list<TenantScope>  $scopes
     * @return list<TenantScope>
     */
    private function filterScopesByActorCeiling(array $scopes): array
    {
        if ($scopes === []) {
            return [];
        }

        try {
            $holding = app(HoldingAccessService::class);
            if ($holding->isGroupWideActor()) {
                return $scopes;
            }
            $allowedCompanies = $holding->actorCeilingCompanyIds();
            $allowedBranches = $holding->actorCeilingBranchIds();
        } catch (\Throwable) {
            return $scopes;
        }

        $out = [];
        foreach ($scopes as $s) {
            $type = strtoupper((string) ($s->scope_type ?? ''));
            $refs = $s->getAttribute('reference_ids');
            if (! is_array($refs) || $refs === []) {
                $rid = (string) ($s->reference_id ?? '');
                $refs = $rid !== '' ? [$rid] : [];
            }
            $refs = array_values(array_unique(array_filter(array_map('strval', $refs))));

            if ($type === 'COMPANY') {
                if ($allowedCompanies === []) {
                    continue;
                }
                if ($refs === [] || array_diff($refs, $allowedCompanies) !== []) {
                    continue;
                }
                $out[] = $s;
                continue;
            }

            if ($type === 'BRANCH') {
                if ($allowedBranches === []) {
                    continue;
                }
                if ($refs === [] || array_diff($refs, $allowedBranches) !== []) {
                    continue;
                }
                $out[] = $s;
                continue;
            }
        }

        return $out;
    }
NEW;
    if (strpos($scope, $sold) === false) {
        throw new RuntimeException('ScopeService patch pattern missing');
    }
    $scope = str_replace($sold, $snew, $scope);
}
file_put_contents($root . '/app/Modules/IdentityCore/Services/ScopeService.php', $scope);
echo 'OK ScopeService bytes=' . strlen($scope) . PHP_EOL;
echo "Done. Commit restored sources and push develop.\n";
