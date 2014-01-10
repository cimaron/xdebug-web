var net = require('net');
var pd = require('pretty-data').pd;
var util = require('util');

/**
 * Write log to stdout if debug enabled
 */
function writeLog() {
	if (options.debug) {
		console.log.apply(console, arguments);
	}	
}
writeLog.pretty = function(data, depth) {
	return util.inspect(data, { colors: true, showHidden: false, depth: depth === false ? 2 : depth });
};

List = (function() {
	
	List = function() {
		this.list = [];	
	}
	
	var proto = List.prototype;
	
	proto.getList = function() {
		return this.list;
	};
	
	proto.add = function(item) {
		this.list.push(item);	
	};
	
	proto.remove = function(item) {
		var i;

		if ((i = this.list.indexOf(item)) != -1) {
			this.list.splice(i, 1);
		}
	};
	
	proto.get = function(i) {
		return this.list[i];
	};
	
	proto.find = function(item, idx) {
		var i;
		
		if ((i = this.list.indexOf(item)) != -1) {
			return idx ? i : this.list[i];
		}
		
		return null;
	};
	
	proto.getLength = function() {
		return this.list.length;
	};

	return List;
}());




Ide = (function() {

	function Ide(socket) {

		this.name = null;
		this.socket = socket;
		this.debugger = null;

		socket.on('message', this.onSocketMessage.bind(this));
		socket.on('close', this.onSocketClose.bind(this));		
	}

	var proto = Ide.prototype;

	proto.getName = function() {
		return this.name;
	};
	
	proto.setName = function(name) {
		this.name = name;
		this.findDebugger();
	};

	proto.findDebugger = function() {
		for (var i = 0; i < debuggers.getLength(); i++) {
			var dbg = debuggers.get(i);
			if (!dbg.isAttached() && dbg.getName() == this.name) {
				this.attach(dbg);
			}
		}
	};

	proto.attach = function(dbg) {

		if (!this.debugger) {
			writeLog(this.getLog('attach', dbg.getLog()));
			this.debugger = dbg;
			dbg.attach(this);
		}
	};

	proto.detach = function() {

		if (this.debugger) {
			var dbg = this.debugger;
			writeLog(this.getLog('detach', dbg.getLog()));
			this.debugger = null;
			dbg.detach();

			var packet = new ProxyPacket('disconnect', dbg.appid);
			this.send(packet.toString());
		}

		this.findDebugger();
	}

	proto.getSocket = function() {
		return this.socket;
	};
	
	/**
	 * Socket Communication
	 */
	proto.send = function(data) {

		if (this.socket) {
			try {
				this.socket.send(data);
				writeLog(this.getLog('socket.send'), ' => ', data);
			} catch (e) {
				writeLog(this.getLog('socket.error', e));
			}
		}
	};

	proto.onSocketClose = function() {

		writeLog(this.getLog('socket.close'));

		ideSockets.remove(this.socket);
		ides.remove(this);

		this.socket = null;

		if (this.debugger) {
			this.detach();
		}
	};

	proto.onSocketMessage = function(data) {
		writeLog(this.getLog('socket.message'), ' <= ', data);
		var result = Commands.execute(data, this);
	};

	proto.getLog = function(action, data) {
		return 'ide[' + (this.name ? this.name : '??') + ']' + (action ? '.' + action : '') + (data ? ': ' + data : '');
	};

	return Ide;

}());


var Debugger = (function() {

	function Debugger(socket) {

		this.socket = socket;
		this.ide = null;

		this.name = null;
		this.appid = null;

		this.buffer = new DebuggerBuffer();
		
		this.cmd_queue = [];
		this.pkt_queue = [];
		socket.on('data', this.onSocketData.bind(this));
		socket.on('end', this.onSocketEnd.bind(this));		
	}

	var proto = Debugger.prototype;
	
	proto.getName = function() {
		return this.name;
	};
	
	proto.isAttached = function() {
		return this.ide ? true : false;
	};

	proto.attach = function(ide) {

		if (this.isAttached()) {
			return false;
		}

		this.ide = ide;
		
		if (this.pkt_queue.length) {
			//if we have queued packets, let's process async so the init doesn't mess up
			setTimeout(this.flushPacketQueue.bind(this), 0);
		}

		return true;
	};

	proto.detach = function() {
		
		if (!this.isAttached()) {
			return false;	
		}

		debuggerSockets.remove(this.socket);
		debuggers.remove(this);

		this.socket.end();

		return true;
	};


	proto.init = function(packet) {
		var name, appid;

		name = packet.match(/idekey="([^"]*)"/);
		appid = packet.match(/appid="([^"]*)"/);

		if (!name) {
			return false;
		}

		this.name = name[1];
		this.appid = appid[1];

		writeLog('    ', this.getLog('init'));

		for (var i = 0; i < ides.getLength(); i++) {
			var ide = ides.get(i);
			if (ide.getName() == this.name) {
				ide.attach(this);
				break;
			}
		}

		return true;
	};

	/**
	 * Socket Communication
	 */
	proto.onSocketData = function(data) {

		writeLog(this.getLog('socket.data'), ' <= ', data);

		var packet;

		this.buffer.addData(data);

		while (packet = this.buffer.getPacket()) {
			writeLog(this.getLog('packet.queue', packet));
			this.pkt_queue.push(packet);
		}

		//see if first init packet
		if (this.pkt_queue.length && !this.appid) {
			var packet = this.pkt_queue[0];
			if (!this.init(packet)) {
				this.detach();
				return;
			}
			this.pkt_queue[0] = new ProxyPacket('connect', packet, this.appid);
		}

		if (this.pkt_queue.length) {
			this.flushPacketQueue();
		}
	};

	proto.flushPacketQueue = function() {

		//start flushing queued packets to debugger
		if (this.pkt_queue.length && this.isAttached()) {
			
			writeLog(this.getLog('queue.flush', this.pkt_queue.length + ' left'));

			var packet = this.pkt_queue.shift();
			
			if (typeof packet == 'object') {
				packet = packet.toString();
			} else {
				var matched = packet.match(/transaction_id="([^"]*)"/);
				if (matched) {
					var transaction_id = matched[1];
				}

				packet = new ProxyPacket('debugger', packet, this.appid, {trans : transaction_id});
			}

			this.ide.send(packet.toString());
			
			writeLog('    ', this.ide.getLog('proxy', packet.toString()));
			
			setTimeout(this.flushPacketQueue.bind(this), 0);
		}
	};

	proto.onSocketEnd = function(data) {

		writeLog(this.getLog('socket.close'));

		if (this.isAttached()) {
			this.ide.send(new ProxyPacket('disconnect', this.appid));
			this.ide.detach(this);
		}
	};

	proto.send = function(data) {
		if (this.socket) {
			writeLog(this.getLog('socket.send'), ' => ', data);
			this.socket.write(data + String.fromCharCode(0));
		}
	};
	
	proto.getLog = function(action, data) {
		return 'debugger[' + (this.name ? this.name : '??') + ']' + (action ? '.' + action : '') + (data ? ': ' + data : '');
	};

	return Debugger;

}());


DebuggerBuffer = (function() {

	function DebuggerBuffer() {
		this.buffer = "";
	}

	var proto = DebuggerBuffer.prototype;

	proto.addData = function(data) {
		this.buffer += new Buffer(data, 'binary').toString();			
	};

	/**
	 * Get a single xml packet
	 *
	 * @return  string
	 */
	proto.getPacket = function() {
		var packet, length, parts, data;

		if (!this.buffer.length) {
			return null;
		}

		if (this.buffer.indexOf(String.fromCharCode(0)) == -1) {
			return false;	
		}

		//split up packet parts
		parts = this.buffer.split(String.fromCharCode(0), 2);
		length = parseInt(parts[0]);
		data = parts[1];

		if (data.length < length) {
			return false;	
		}

		packet = data.substring(0, length);
		this.buffer = data.substring(length + 1);

		packet = pd.xml(packet);

		return packet;
	};
	
	return DebuggerBuffer;
}());


var ProxyPacket = (function() {

	function ProxyPacket(type, data, appid, request) {
		this.type = type;
		this.data = data;
		this.appid = appid;
		this.request = request;
	}
	
	var proto = ProxyPacket.prototype;
	
	proto.toString = function() {
		return JSON.stringify(this);
	};

	return ProxyPacket;

}());



var ides = new List();
var ideSockets = new List();
var debuggers = new List();
var debuggerSockets = new List();










/**
 * Commands Class
 */
var Commands = {
	
	last_id : 0,
	
	/**
	 * Execute command
	 */
	execute : function(cmd, ide) {
		
		//writeLog('command.execute: ', cmd);

		try {
			var command = JSON.parse(cmd);
		} catch (e) {
			writeLog(e);
			return;
		}

		if (command && command.method && typeof this[command.method] == 'function') {
			this[command.method](command, ide);
		} else {
			ide.send('Unknown command: ' + command.method);	
		}
	},

	/**
	 * Set WebSocket Client Name
	 *
	 * @param   string   message.name   Name to set
	 */
	setName : function(cmd, ide) {
		writeLog('    ', ide.getLog('command.setName', cmd.data));
		
		ide.setName(cmd.data);

		var response = new ProxyPacket('setName', ide.getName(), null, cmd);

		ide.send(response.toString());
	},

	debug : function(cmd, ide) {

		var dbgCmd = cmd.data;
		
		if (ide.debugger) {
		
			ide.debugger.send(dbgCmd);	

		} else {

			var response = new ProxyPacket('error', false, null, {error : 'Not connected'});
			ide.send(response.toString());
		}		
	},

	help : function(cmd, ide) {

		var response = new ProxyPacket('help', null, null, cmd);

		switch (cmd.data) {

			case 'setName':
				response.data = 'setName: Set the name for this client session';
				break;
			
			case 'debug':
				response.data = 'debug: Send a command to the debugger'
				break;
			
			default:
				response.data = "Options:\ndebug\nsetName"
		}

		ide.send(response.toString());
	}
};



var args = process.argv.slice(2);
var options = {
	debug : (args.indexOf('-d') != -1)
};


// Create a new server and provide a callback for when a connection occurs
var server = net.createServer(function(socket) {
	writeLog('debugger.socket.open');
	debuggerSockets.add(socket);
	var dbg = new Debugger(socket);
	debuggers.add(dbg);
});



server.on('error', function(e) {
	if (e.code == 'EADDRINUSE') {
		console.log('Address in use, retrying...');
		setTimeout(function() {
			server.close();
			server.listen(PORT, HOST);
		}, 1000);
	}
});

// Listen on port 9000
server.listen(9000, function() {
	writeLog('Server started');
});


/**
 * WebSocket Server
 */
var WebSocketServer = require('ws').Server;
var wss = new WebSocketServer({port: 1984});
wss.on('connection', function(socket) {
	writeLog('ide.socket.open');
	ideSockets.add(socket);
	var ide = new Ide(socket);
	ides.add(ide);
});


