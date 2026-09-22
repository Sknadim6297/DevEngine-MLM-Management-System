@php
    $children = $node['children'] ?? [];
    $hasChildren = count($children) > 0;
    $status = strtolower((string) ($node['status'] ?? 'inactive'));
@endphp

<li data-member-node data-member-id="{{ $node['id'] }}" data-member-status="{{ $status }}">
    <div class="tree-member {{ $status === 'active' ? 'is-active' : 'is-inactive' }}">
        @if ($hasChildren)
            <button type="button" class="tree-toggle" data-tree-toggle aria-expanded="true" aria-label="Toggle {{ $node['name'] }} branch">
                <i class="bi bi-chevron-down"></i>
            </button>
        @else
            <span class="tree-toggle-spacer" aria-hidden="true"></span>
        @endif
        <span class="tree-member-icon"><i class="bi bi-person-circle"></i></span>
        <span class="tree-member-content">
            <strong>{{ $node['name'] }}</strong>
            <small>{{ $node['id'] }}</small>
        </span>
        <span class="tree-member-meta">
            <span class="tree-status {{ $status === 'active' ? 'status-active' : 'status-inactive' }}">{{ ucfirst($status) }}</span>
            @if ($hasChildren)
                <span class="tree-child-count">{{ count($children) }} {{ count($children) === 1 ? 'child' : 'children' }}</span>
            @endif
        </span>
    </div>

    @if ($hasChildren)
        <ul class="tree-children">
            @foreach ($children as $child)
                @include('member.genealogy.partials.tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
