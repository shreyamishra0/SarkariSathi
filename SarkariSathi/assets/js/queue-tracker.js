let queueInterval;

function startQueueTracking(queueId) {
    // Update immediately
    updateQueueStatus(queueId);
    
    // Poll every 10 seconds
    queueInterval = setInterval(() => {
        updateQueueStatus(queueId);
    }, 10000);
}

function updateQueueStatus(queueId) {
    fetch(`/api/get-queue-status.php?queue_id=${queueId}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('current-serving').textContent = data.current_serving;
            document.getElementById('my-number').textContent = data.my_number;
            document.getElementById('people-ahead').textContent = data.people_ahead;
            document.getElementById('wait-time').textContent = data.estimated_wait + ' min';
            
            // Alert if turn is near
            if (data.people_ahead <= 2) {
                showNotification('Your turn is coming soon!');
            }
        });
}

function stopQueueTracking() {
    clearInterval(queueInterval);
}
