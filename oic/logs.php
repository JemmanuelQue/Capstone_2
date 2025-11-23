<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Enforce OIC role (Role_ID = 8)
if (!validateSession($conn, 8)) { exit; }

// Get OIC profile data
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
$oicProfile = ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic']))
	? $profileData['Profile_Pic']
	: '../images/default_profile.png';
$oicName = $profileData ? ($profileData['First_Name'] . ' ' . $profileData['Last_Name']) : htmlspecialchars($_SESSION['name'] ?? 'OIC User');

// Fetch activity logs only for this OIC user
$logsStmt = $conn->prepare("SELECT Log_ID, Activity_Type, Activity_Details, Timestamp FROM activity_logs WHERE User_ID = ? ORDER BY Timestamp DESC");
$logsStmt->execute([$_SESSION['user_id']]);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Build distinct activity types for filter UI
$activityTypes = [];
foreach ($logs as $l) { $activityTypes[$l['Activity_Type']] = true; }
$activityTypes = array_keys($activityTypes);
sort($activityTypes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Activity Logs - OIC | Green Meadows Security Agency</title>
	<!-- Bootstrap 5 -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<!-- Google Material Icons -->
	<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
	<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
	<!-- DataTables CSS -->
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
	<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
	<!-- Daterange Picker CSS -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
	<!-- Custom OIC CSS (reuse dashboard styling) -->
	<link rel="stylesheet" href="css/oic_dashboard.css">
	<style>
		.page-title { font-weight:600; }
		.dt-buttons .btn { margin-right: .5rem; }
		.table-wrap { background:#fff; padding:20px; border-radius:12px; box-shadow:0 0 10px rgba(0,0,0,.05); overflow-x:auto; }
		/* Allow wrapping of long details */
		table.dataTable tbody td { white-space: normal !important; }
		.activity-filters .form-check { margin-right:1rem; }
		.filter-label { font-size:.85rem; text-transform:uppercase; letter-spacing:.5px; font-weight:600; color:#495057; }
		#exportButtons .btn { margin-right:.5rem; }
	</style>
</head>
<body>
	<?php include '../global/oic_sidebar.php'; ?>
	<div class="main-content" id="main-content">
		<div class="header">
			<button class="toggle-sidebar" id="toggleSidebar"><span class="material-icons">menu</span></button>
			<div class="current-datetime ms-3 d-none d-md-block">
				<span id="current-date"></span> | <span id="current-time"></span>
			</div>
			<a href="profile.php" class="user-profile" style="color:black; text-decoration:none;">
				<span><?php echo htmlspecialchars($oicName); ?></span>
				<img src="<?php echo htmlspecialchars($oicProfile); ?>" alt="Profile">
			</a>
		</div>
		<div class="container-fluid mt-4">
			<h1 class="page-title mb-3"><span class="material-icons align-middle me-1">receipt_long</span> My Activity Logs</h1>
			<p class="text-muted">These are all system-recorded activities associated with your account. Use filters below and export filtered results as PDF.</p>

			<!-- Filters Row (white background) -->
			<div class="filters-container bg-white p-3 rounded shadow-sm mb-3">
				<div class="row g-3 align-items-end">
					<div class="col-md-3">
						<label for="activityType" class="filter-label">Activity Type</label>
						<select id="activityType" class="form-select">
							<option value="">All</option>
							<?php foreach ($activityTypes as $type): ?>
								<option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label for="dateRange" class="filter-label">Date Range</label>
						<input type="text" id="dateRange" class="form-control" placeholder="Select range" />
					</div>
					<div class="col-md-3">
						<button id="clearFilters" class="btn btn-outline-secondary mt-2">Clear Filters</button>
					</div>
				</div>
			</div>
			<!-- Export Buttons Row (aligned right) -->
			<div class="d-flex justify-content-end mb-3" id="exportButtonsWrapper">
				<div id="exportButtons"></div>
			</div>

			<div class="table-wrap mt-3">
				<table id="logsTable" class="display" style="width:100%">
					<thead>
						<tr>
							<th>Activity Type</th>
							<th>Details</th>
							<th>Timestamp</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($logs)): ?>
							<?php foreach ($logs as $log): ?>
								<tr>
									<td><?php echo htmlspecialchars($log['Activity_Type']); ?></td>
									<td><?php echo htmlspecialchars($log['Activity_Details']); ?></td>
									<td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['Timestamp']))); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				<?php if (empty($logs)): ?>
					<div class="alert alert-info mb-0">No activity logs found for your account.</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<!-- DataTables & Buttons -->
	<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
	<!-- PDF export dependencies -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
	<!-- Moment & Daterange Picker -->
	<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
	<!-- SweetAlert2 for toast notifications -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<script>
		// DateTime display
		function updateDateTime() {
			const now = new Date();
			document.getElementById('current-date').textContent = now.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
			document.getElementById('current-time').textContent = now.toLocaleTimeString('en-PH');
		}
		setInterval(updateDateTime, 1000); updateDateTime();

		// Sidebar toggle
		document.getElementById('toggleSidebar').addEventListener('click', function(){
			document.getElementById('sidebar').classList.toggle('collapsed');
			document.getElementById('main-content').classList.toggle('expanded');
		});

		// Initialize DataTable (updated after removing Log ID column)
		$(document).ready(function(){
			let startDate = null, endDate = null;
			const activityCol = 0; // Activity Type column index

			// Custom date range filter
			$.fn.dataTable.ext.search.push(function(settings, data){
				if(!startDate || !endDate) return true; // no filter applied
				const ts = data[2]; // Timestamp column string (index 2)
				const logMoment = moment(ts, 'YYYY-MM-DD HH:mm:ss');
				if(!logMoment.isValid()) return false;
				return logMoment.isSameOrAfter(startDate) && logMoment.isSameOrBefore(endDate);
			});

			const table = $('#logsTable').DataTable({
				responsive: true,
				pageLength: 25,
				lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'All']],
				order: [[2, 'desc']], // order by Timestamp
				dom: 'lfrtip'
			});

			// Export buttons
			new $.fn.dataTable.Buttons(table, {
				buttons: [
					{
						extend: 'pdfHtml5',
						className: 'btn btn-danger btn-sm',
						title: 'My Activity Logs',
						orientation: 'portrait',
						pageSize: 'A4',
						exportOptions: { columns: [0,1,2], modifier: { search: 'applied' } },
						customize: function (doc) {
							doc.styles.tableHeader.alignment = 'left';
							if(doc.content[1] && doc.content[1].table){
								doc.content[1].table.widths = ['20%','60%','20%'];
							}
						}
					},
					{
						extend: 'print',
						className: 'btn btn-secondary btn-sm',
						title: 'My Activity Logs',
						exportOptions: { columns: [0,1,2], modifier: { search: 'applied' } }
					}
				]
			}).container().appendTo('#exportButtons');

			function showToast(message){
				Swal.fire({
					toast: true,
					position: 'top-end',
					icon: 'info',
					showConfirmButton: false,
					timer: 1000,
					timerProgressBar: true,
					title: message
				});
			}

			// Activity dropdown filter
			$('#activityType').on('change', function(){
				const val = this.value.trim();
				if(val === '') {
					table.column(activityCol).search('').draw();
					showToast('Showing all activity types');
				} else {
					const pattern = '^' + val.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + '$';
					table.column(activityCol).search(pattern, true, false).draw();
					showToast('Filtered by: ' + val);
				}
			});

			// Date range picker
			$('#dateRange').daterangepicker({
				autoUpdateInput: false,
				timePicker: false,
				locale: { cancelLabel: 'Clear' },
				ranges: {
					'Last 7 Days': [moment().subtract(6,'days'), moment()],
					'Last 30 Days': [moment().subtract(29,'days'), moment()],
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1,'month').startOf('month'), moment().subtract(1,'month').endOf('month')]
				}
			});

			$('#dateRange').on('apply.daterangepicker', function(ev, picker){
				startDate = picker.startDate.startOf('day');
				endDate = picker.endDate.endOf('day');
				$(this).val(startDate.format('YYYY-MM-DD') + ' to ' + endDate.format('YYYY-MM-DD'));
				table.draw();
				showToast('Date range applied');
			});
			$('#dateRange').on('cancel.daterangepicker', function(){
				$(this).val('');
				startDate = null; endDate = null; table.draw();
				showToast('Date range cleared');
			});

			// Clear filters button
			$('#clearFilters').on('click', function(){
				$('#activityType').val('');
				$('#dateRange').val('');
				startDate = null; endDate = null;
				table.column(activityCol).search('');
				table.search('');
				table.draw();
				showToast('All filters cleared');
			});
		});
	</script>
</body>
</html>
