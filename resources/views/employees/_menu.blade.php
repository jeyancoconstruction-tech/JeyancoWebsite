<div class="rm-menu-wrap">
    <button type="button" class="rm-menu-btn" aria-label="More actions"><i class="fas fa-ellipsis-v"></i></button>
    <div class="rm-menu">
        @if($context === 'active')
            <form action="{{ route('employees.archive', $e->id) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit" class="rm-menu-item"><i class="fas fa-box-archive"></i> Archive (left company)</button>
            </form>
        @endif

        @if($context === 'archived')
            <form action="{{ route('employees.activate', $e->id) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit" class="rm-menu-item ok"><i class="fas fa-rotate-left"></i> Reactivate</button>
            </form>
        @endif

        @if($context === 'removed')
            <form action="{{ route('employees.restore', $e->id) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit" class="rm-menu-item ok"><i class="fas fa-trash-can-arrow-up"></i> Restore</button>
            </form>
            <form action="{{ route('employees.force-delete', $e->id) }}" method="POST"
                  onsubmit="return confirm('Permanently delete {{ addslashes($e->name) }} and ALL their attendance? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="rm-menu-item danger"><i class="fas fa-trash"></i> Delete permanently</button>
            </form>
        @else
            <form action="{{ route('employees.destroy', $e->id) }}" method="POST"
                  onsubmit="return confirm('Remove {{ addslashes($e->name) }}? Their records are preserved and can be restored.')">
                @csrf @method('DELETE')
                <button type="submit" class="rm-menu-item danger"><i class="fas fa-user-minus"></i> Remove</button>
            </form>
        @endif
    </div>
</div>
