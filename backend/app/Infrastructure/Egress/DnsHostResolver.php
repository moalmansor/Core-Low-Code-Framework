<?php

declare(strict_types=1);

namespace App\Infrastructure\Egress;

final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = (string) $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $addresses[] = (string) $record['ipv6'];
            }
        }
        if ($addresses === []) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($addresses));
    }
}
