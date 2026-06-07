function greet(name) {
    var message = 'Hello, ' + name + '!';
    console.log(message);
    return message;
}

var app = {
    version: '1.0.0',
    init: function () {
        greet('World');
    }
};