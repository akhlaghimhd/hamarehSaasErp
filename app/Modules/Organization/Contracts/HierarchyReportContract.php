<?php

namespace App\Modules\Organization\Contracts;

/**
 * ORG-W2-03 — Cross-module read contract for purpose-based org hierarchies.
 *
 * Reporting / Accounting / Analytics depend on this interface only
 * (Law 2.3 — no physical FK, no direct table access across modules).
 */
interface HierarchyReportContract
{
    /**
     * Active hierarchy header for a purpose (highest version), or null.
     *
     * @return array{hierarchy_id:string,code:string,name:string,purpose:string,version:int,valid_from:?string,valid_to:?string}|null
     */
    public function activeHierarchy(string $tenantId, string $purpose): ?array;

    /**
     * Flat node list for the active hierarchy of the given purpose.
     *
     * @return list<array{
     *   node_id:string,
     *   parent_node_id:?string,
     *   entity_type:string,
     *   entity_id:string,
     *   sort_order:int,
     *   is_active:bool
     * }>
     */
    public function nodesByPurpose(string $tenantId, string $purpose): array;

    /**
     * Nested tree for the active hierarchy of the given purpose.
     *
     * @return list<array{node_id:string,entity_type:string,entity_id:string,children:array}>
     */
    public function treeByPurpose(string $tenantId, string $purpose): array;

    /**
     * Entity ids of the same entity_type under a root entity within a purpose tree
     * (root itself included).
     *
     * @return list<string>
     */
    public function descendantEntityIds(
        string $tenantId,
        string $purpose,
        string $entityType,
        string $entityId
    ): array;
}
