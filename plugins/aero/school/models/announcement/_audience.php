<?php
if ($value === 'course') { echo e($record->course?->name ?? 'Un curso'); }
elseif ($value === 'level') { echo e(\Aero\School\Models\Course::LEVELS[$record->level] ?? 'Un nivel'); }
else { echo 'Todo el colegio'; }
?>
