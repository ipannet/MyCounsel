// Function to sort table
function sortTable(column) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'all';
    const currentSortBy = urlParams.get('sort_by') || 'date';
    const currentSortOrder = urlParams.get('sort_order') || 'asc';
    const searchQuery = urlParams.get('search') || '';
    
    let newSortOrder = 'asc';
    if (column === currentSortBy && currentSortOrder === 'asc') {
        newSortOrder = 'desc';
    }
    
    let url = `admin-appointments.php?tab=${currentTab}&sort_by=${column}&sort_order=${newSortOrder}`;
    if (searchQuery) {
        url += `&search=${encodeURIComponent(searchQuery)}`;
    }
    
    window.location.href = url;
}