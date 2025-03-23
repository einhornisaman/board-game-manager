// Board Game Manager filters script
jQuery(document).ready(function($) {
    // Define custom search functions first
    $.fn.dataTable.ext.search = []; // Clear any existing search functions
    
    $.fn.dataTable.ext.search.push(
        // Game Time Filter
        function (settings, data, dataIndex) {
            var iMin = parseInt($('#min').val(), 10);
            var iMax = parseInt($('#max').val(), 10);
        
            // Default values if input is empty or invalid
            iMin = isNaN(iMin) ? 0 : iMin;
            iMax = isNaN(iMax) ? 9999 : iMax;
        
            var minTime = parseInt(data[4], 10);
            var maxTime = parseInt(data[5], 10);
        
            // Default values for games with missing/invalid time data
            minTime = isNaN(minTime) ? 0 : minTime;
            if (isNaN(maxTime) || maxTime === 0) {
                maxTime = minTime; // If maxTime is 0 or missing, set it to minTime
            }
        
            // The game should be included if:
            // - Its min time is at least the selected min time
            // - Its max time does not exceed the selected max time
            if (minTime >= iMin && maxTime <= iMax) {
                return true;
            }
        
            return false;
        },
        
        // Player Count Filter
        function(settings, data, dataIndex) {
            var selectedPlayerCount = $('#filterPlayerCount').val();
            var minPlayers = parseInt(data[2], 10) || 0;
            var maxPlayers = parseInt(data[3], 10) || 0;
        
            if (!selectedPlayerCount || selectedPlayerCount === "") {
                return true;
            }
        
            if (selectedPlayerCount === "999") {
                return minPlayers === 2 && maxPlayers === 2;
            }
        
            selectedPlayerCount = parseInt(selectedPlayerCount, 10);
            return minPlayers <= selectedPlayerCount && maxPlayers >= selectedPlayerCount;
        },

        // Complexity Filter
        function (settings, data, dataIndex) {
            var complexityMin = parseFloat($('#complexityMin').val()) || 0;
            var complexityMax = parseFloat($('#complexityMax').val()) || 9999;
            var complexity = parseFloat(data[6]) || 0;

            if (complexityMin === 0 && complexityMax === 9999) {
                return true;
            }
            
            return (complexity >= complexityMin && complexity <= complexityMax);
        }
    );

    // Initialize DataTable
    var table = $('.mydatatable').DataTable({
        "language": {
            "infoFiltered": " of _MAX_"
        },
        "paging": true,
        "pagingType": $(window).width() < 768 ? "simple_numbers" : "simple_numbers",
        "processing": true,
        "responsive": true,
        "order": [[10, "desc"]],
        "initComplete": function(settings, json) {
            $('.wrapper').removeClass('loading');
        },
        "columnDefs": [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: 1 },
            { type: 'accent-neutralise', targets: 1 }
        ],
        "drawCallback": function(settings) {
            // Only scroll to top if the draw was triggered by pagination
            if (settings.iDraw > 1 && settings._iDisplayStart !== settings._iDisplayStart_) {
                window.scrollTo(0, 0); // Scroll to the top only on pagination change
            }
            // Store the current display start for future comparison
            settings._iDisplayStart_ = settings._iDisplayStart;
        }
    });

    // Player Count filter
    $('#filterPlayerCount').on('change', function() {
        table.draw();
    });

    // Complexity filter
    $('#filterComplexity').on('change', function() {
        switch($(this).val()) {
            case '1':
                $('#complexityMin').val('0');
                $('#complexityMax').val('1.49');
                break;
            case '2':
                $('#complexityMin').val('1.5');
                $('#complexityMax').val('1.99');
                break;
            case '3':
                $('#complexityMin').val('2');
                $('#complexityMax').val('2.49');
                break;
            case '4':
                $('#complexityMin').val('2.5');
                $('#complexityMax').val('2.99');
                break;
            case '5':
                $('#complexityMin').val('3');
                $('#complexityMax').val('3.49');
                break;
            case '6':
                $('#complexityMin').val('3.5');
                $('#complexityMax').val('5');
                break;
            default:
                $('#complexityMin').val('0');
                $('#complexityMax').val('9999');
                break;
        }
        table.draw();
    });

    // Game Time filter
    $('#filterGameTime').on('change', function() {
        switch($(this).val()) {
            case '1':
                $('#min').val('0');
                $('#max').val('15');
                break;
            case '2':
                $('#min').val('15');
                $('#max').val('30');
                break;
            case '3':
                $('#min').val('30');
                $('#max').val('60');
                break;
            case '4':
                $('#min').val('60');
                $('#max').val('120');
                break;
            case '5':
                $('#min').val('120');
                $('#max').val('9999');
                break;
            default:
                $('#min').val('0');
                $('#max').val('9999');
                break;
        }
        table.draw();
    });

    // Category filter
    $('#filterCategory').on('change', function() {
        table.column(7).search($(this).val()).draw();
    });

    // Mechanic filter
    $('#filterMechanic').on('change', function() {
        table.column(8).search($(this).val()).draw();
    });

    // Reset all filters
    $('#resetFilters').on('click', function() {
        // Reset all select dropdowns
        $('#filterPlayerCount, #filterComplexity, #filterGameTime, #filterCategory, #filterMechanic').val('');
        
        // Reset hidden range values
        $('#min, #complexityMin').val('0');
        $('#max, #complexityMax').val('9999');
        
        // Clear all column searches and redraw
        table.search('').columns().search('').draw();

        // Reset sorting to default (remove any active sorting)
        table.order([10, 'desc']).draw();
    });
});