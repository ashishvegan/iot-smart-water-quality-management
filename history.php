<?php
/**
 * Sensor Records History with Pagination & CSV Export
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/includes/functions.php';

// Authentication requirement
require_login();

// Request parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Fetch paginated data
$dataResult = get_paginated_records($page, $limit, $statusFilter);
$records = $dataResult['records'];
$totalPages = $dataResult['totalPages'];
$currentPage = $dataResult['page'];
$totalRecords = $dataResult['total'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
      Sensor Telemetry Records
      <span class="text-xs px-2.5 py-1 rounded-full bg-sky-500/20 text-sky-300 border border-sky-500/30 font-mono font-normal">
        Total: <?= number_format($totalRecords) ?> Logs
      </span>
    </h1>
    <p class="text-slate-400 text-xs sm:text-sm mt-1">Full chronological audit of water quality parameters and valve state</p>
  </div>

  <div class="flex items-center gap-3 flex-wrap">
    <!-- Export CSV Button (Requirement 16) -->
    <a href="export.php" class="btn btn-sm btn-info gap-2 shadow-lg shadow-sky-500/20">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
  </div>
</div>

<!-- FILTER & PAGINATION CONTROL BAR -->
<div class="water-card p-4 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 text-sm">
  <!-- Status Filters -->
  <div class="flex items-center gap-2 flex-wrap">
    <span class="text-xs text-slate-400 font-semibold uppercase">Filter Status:</span>
    <a href="history.php?status=all&limit=<?= $limit ?>" class="btn btn-xs <?= ($statusFilter === 'all') ? 'btn-info' : 'btn-ghost border border-slate-700' ?>">All Logs</a>
    <a href="history.php?status=good&limit=<?= $limit ?>" class="btn btn-xs <?= ($statusFilter === 'good') ? 'btn-success' : 'btn-ghost border border-slate-700' ?>">Safe Water</a>
    <a href="history.php?status=warning&limit=<?= $limit ?>" class="btn btn-xs <?= ($statusFilter === 'warning') ? 'btn-warning' : 'btn-ghost border border-slate-700' ?>">Warnings</a>
    <a href="history.php?status=bad&limit=<?= $limit ?>" class="btn btn-xs <?= ($statusFilter === 'bad') ? 'btn-error' : 'btn-ghost border border-slate-700' ?>">Critical Bad</a>
  </div>

  <!-- Rows per page selector -->
  <form method="GET" action="history.php" class="flex items-center gap-2">
    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
    <span class="text-xs text-slate-400 font-semibold uppercase">Per Page:</span>
    <select name="limit" onchange="this.form.submit()" class="select select-bordered select-xs bg-slate-900 text-slate-200 border-slate-700">
      <option value="10" <?= ($limit == 10) ? 'selected' : '' ?>>10 records</option>
      <option value="20" <?= ($limit == 20) ? 'selected' : '' ?>>20 records</option>
      <option value="50" <?= ($limit == 50) ? 'selected' : '' ?>>50 records</option>
      <option value="100" <?= ($limit == 100) ? 'selected' : '' ?>>100 records</option>
    </select>
  </form>
</div>

<!-- RECORDS TABLE -->
<div class="water-card overflow-hidden mb-6">
  <div class="overflow-x-auto">
    <table class="table table-zebra w-full text-left text-sm">
      <thead class="bg-slate-900/90 text-xs uppercase font-mono text-sky-400 border-b border-sky-900/40">
        <tr>
          <th>Timestamp</th>
          <th>TDS (ppm)</th>
          <th>Turbidity (NTU)</th>
          <th>pH Level</th>
          <th>Temp (°C)</th>
          <th>Quality Score</th>
          <th>Relay 1 SV</th>
          <th>Issues / Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800/60 font-mono text-xs">
        <?php if (empty($records)): ?>
          <tr>
            <td colspan="8" class="text-center py-8 text-slate-500 font-sans">
              No sensor records found for the selected filter.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($records as $row): ?>
            <tr class="hover:bg-sky-950/20 transition-colors">
              <td class="whitespace-nowrap text-slate-300 font-sans font-medium">
                <?= htmlspecialchars(format_datetime(isset($row['epoch']) ? $row['epoch'] : $row['timestamp'])) ?>
              </td>
              <td>
                <span class="font-bold text-sky-400"><?= number_format($row['tds'], 1) ?></span>
              </td>
              <td>
                <span class="font-bold text-amber-400"><?= number_format($row['turbidity'], 2) ?></span>
              </td>
              <td>
                <span class="font-bold <?= ($row['ph'] < 6.5 || $row['ph'] > 8.5) ? 'text-rose-400' : 'text-emerald-400' ?>">
                  <?= number_format($row['ph'], 2) ?>
                </span>
              </td>
              <td class="text-orange-400">
                <?= number_format($row['temperature'], 1) ?>°C
              </td>
              <td>
                <?php if (isset($row['status']) && $row['status'] === 'empty'): ?>
                  <span class="badge badge-sm font-sans font-semibold badge-info">
                    Standby
                  </span>
                <?php else: ?>
                  <span class="badge badge-sm font-sans font-semibold <?= ($row['status'] === 'bad') ? 'badge-error' : (($row['status'] === 'warning') ? 'badge-warning' : 'badge-success') ?>">
                    <?= intval(isset($row['score']) ? $row['score'] : 100) ?>%
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($row['valve_state'])): ?>
                  <span class="badge badge-sm badge-success gap-1 font-sans">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span> OPEN
                  </span>
                <?php else: ?>
                  <span class="badge badge-sm badge-error gap-1 font-sans">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-300"></span> SHUT
                  </span>
                <?php endif; ?>
              </td>
              <td class="font-sans text-[11px] max-w-xs truncate text-slate-400">
                <?php if (!empty($row['issues']) && is_array($row['issues'])): ?>
                  <?php $isDry = (isset($row['status']) && $row['status'] === 'empty'); ?>
                  <span class="<?= $isDry ? 'text-sky-300 font-medium' : 'text-rose-300' ?>" title="<?= htmlspecialchars(implode(', ', $row['issues'])) ?>">
                    <?= htmlspecialchars(implode(', ', $row['issues'])) ?>
                  </span>
                <?php else: ?>
                  <span class="text-emerald-400 font-medium">All Parameters Safe</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- PAGINATION CONTROLS (Requirement 15) -->
<?php if ($totalPages > 1): ?>
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div class="text-xs text-slate-400 font-mono">
      Page <?= $currentPage ?> of <?= $totalPages ?> (Total <?= number_format($totalRecords) ?> items)
    </div>

    <div class="join border border-slate-800 bg-slate-900">
      <!-- Previous Page -->
      <a href="history.php?page=<?= max(1, $currentPage - 1) ?>&limit=<?= $limit ?>&status=<?= htmlspecialchars($statusFilter) ?>" 
         class="join-item btn btn-sm btn-ghost <?= ($currentPage <= 1) ? 'btn-disabled' : '' ?>">
        « Prev
      </a>

      <!-- Page Numbers Range -->
      <?php
      $startPage = max(1, $currentPage - 2);
      $endPage = min($totalPages, $currentPage + 2);
      for ($p = $startPage; $p <= $endPage; $p++):
      ?>
        <a href="history.php?page=<?= $p ?>&limit=<?= $limit ?>&status=<?= htmlspecialchars($statusFilter) ?>" 
           class="join-item btn btn-sm <?= ($p === $currentPage) ? 'btn-info' : 'btn-ghost' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>

      <!-- Next Page -->
      <a href="history.php?page=<?= min($totalPages, $currentPage + 1) ?>&limit=<?= $limit ?>&status=<?= htmlspecialchars($statusFilter) ?>" 
         class="join-item btn btn-sm btn-ghost <?= ($currentPage >= $totalPages) ? 'btn-disabled' : '' ?>">
        Next »
      </a>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
