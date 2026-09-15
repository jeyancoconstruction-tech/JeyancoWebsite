{{-- The save bar at the foot of every System Settings form. Clean, it says
     so; dirty, it names what changed. Behaviour is in _form-script. --}}
<div class="savebar clean" data-savebar>
    <span class="when-clean"><i data-lucide="circle-check" class="ok"></i> All changes saved</span>
    <span class="dot when-dirty"></span>
    <span class="when-dirty"><b data-dirty-count>1 unsaved change</b> <span class="muted" data-dirty-names></span></span>
    <span class="sp"></span>
    <button type="button" class="sx-btn sm quiet" data-discard>Discard</button>
    <button type="submit" class="sx-btn sm primary" data-save><i data-lucide="save"></i> Save changes</button>
</div>
