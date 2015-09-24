var express = require('express')
    , http = require('http')
    , path = require('path')
    , app = express()

// all environments
app.set('port', process.env.PORT || 17000);

app.use(express.static(path.join(__dirname, './')));

http.createServer(app).listen(app.get('port'), function () {
    console.log("Express server listening on port %d in %s mode", app.get('port'), app.get('env'));
});
