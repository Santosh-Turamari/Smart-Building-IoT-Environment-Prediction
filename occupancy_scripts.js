$(document).ready(function() {
    console.log('occupancy_scripts.js loaded');
    $('#predictForm').on('submit', function(e) {
        e.preventDefault();
        const day = $('#day').val();
        const hour = $('#hour').val();
        console.log('Submitting prediction with day:', day, 'hour:', hour);
        $.ajax({
            url: 'predict.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ day: day, hour: hour }),
            success: function(response) {
                console.log('Prediction response:', response);
                $('#result').html(`Predicted Occupants: <span style="color:#00ff99;">${response.prediction}</span>`);
            },
            error: function(xhr, status, error) {
                console.error('Prediction failed:', status, error, xhr.responseText);
                $('#result').html('<span style="color:red;">Prediction failed: ' + xhr.status + ' ' + error + '</span>');
            }
        });
    });
});

function toggleChatbot() {
    const chatbot = document.getElementById('chatbot-container');
    console.log('Chatbot toggle state:', chatbot.style.display);
    chatbot.style.display = chatbot.style.display === 'none' ? 'flex' : 'none';
}

function sendMessage() {
    const userMsg = $('#userMessage').val().trim();
    if (!userMsg) {
        console.log('Empty message, ignoring');
        $('#chatbox').append('<div><b>Bot:</b> Please enter a message.</div>');
        return;
    }
    console.log('Sending message:', userMsg);
    $('#chatbox').append(`<div><b>You:</b> ${userMsg}</div>`);
    $('#userMessage').val('');
    
    $.ajax({
        url: 'chatbot.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ message: userMsg }),
        success: function(response) {
            console.log('Chatbot response:', response);
            if (response.reply) {
                $('#chatbox').append(`<div><b>Bot:</b> ${response.reply}</div>`);
            } else {
                $('#chatbox').append('<div><b>Bot:</b> Invalid response from server.</div>');
            }
            $('#chatbox').scrollTop($('#chatbox')[0].scrollHeight);
        },
        error: function(xhr, status, error) {
            console.error('Chatbot request failed:', status, error, xhr.responseText);
            $('#chatbox').append(`<div><b>Bot:</b> Failed to get response: ${xhr.status} ${error}</div>`);
            $('#chatbox').scrollTop($('#chatbox')[0].scrollHeight);
        }
    });
}