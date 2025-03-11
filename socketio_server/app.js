var app = require('http').createServer(handler);
var io = require('socket.io')(app);

var Redis = require('ioredis');
var redis = new Redis({
  port: 6379,
  host: '35.189.168.14',
  family: 4,
  password: 'password1234',
  db: 0
})


app.listen(6001, '0.0.0.0');

function handler(req, res) {
    res.writeHead(200);
    res.end('');
}

io.on('connection', function(socket) {
  console.log('connection id:' + socket.id);
  socket.on('disconnect', function(){
    console.log('disconnected id:' + socket.id);
  });
});


redis.subscribe('notification-channel', function(err, count) {
  console.log('connect!');
});


redis.on('message', function(channel, message) {
  console.log(message);
  message = JSON.parse(message);
  try {
    if (message && message.data && message.data.account) {
      io.emit(message.data.account, message.data.message);
    }
  } catch(error) {
    //console.error(error);
  }

});