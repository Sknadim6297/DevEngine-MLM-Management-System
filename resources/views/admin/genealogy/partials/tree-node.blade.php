@php
    $hasChildren = !empty($node['children'] ?? []);
@endphp

<li>
    <div class="tree-member">
        <i class="bi bi-person-circle"></i>
        <div>
            <strong>{{ $node['name'] }}</strong>
            <small>({{ $node['id'] }})</small>
            @if (!empty($node['status']))
                <small class="text-muted"> - {{ ucfirst($node['status']) }}</small>
            @endif
        </div>
    </div>

    @if ($hasChildren)
        <ul>
            @foreach ($node['children'] as $child)
                @include('admin.genealogy.partials.tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
