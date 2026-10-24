<div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>{{ $heading }}</th><th class="text-end">Leads</th><th class="text-end">Won</th><th class="text-end">Lost</th><th class="text-end">Conversion</th></tr></thead><tbody>
    @forelse($rows as $r)
        <tr><td>{{ $r[$labelKey] }}</td><td class="text-end">{{ $r['total'] }}</td><td class="text-end text-success">{{ $r['won'] }}</td><td class="text-end text-danger">{{ $r['lost'] }}</td>
            <td class="text-end"><span class="badge badge-soft-{{ $r['rate'] >= 20 ? 'success' : ($r['rate'] >= 8 ? 'warning' : 'secondary') }}">{{ $r['rate'] }}%</span></td></tr>
    @empty <tr><td colspan="5"><x-empty message="No leads in this period." icon="funnel" /></td></tr> @endforelse
</tbody></table></div>
