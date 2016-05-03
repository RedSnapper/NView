#Sio

##Sio Tables

```sql
CREATE TABLE sio_user (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(68) NOT NULL DEFAULT '',
  password varchar(250) DEFAULT NULL,
  email text,
  emailp text,
  active char(2) DEFAULT 'on',
  ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY username (username),
  UNIQUE KEY ausername (username,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_userprofile (
  user int(11) NOT NULL AUTO_INCREMENT,
  ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_userroles (
  user int(11) NOT NULL DEFAULT '0',
  role int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (user,role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_activity (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL DEFAULT 'unknown',
  active char(2) DEFAULT 'on',
  PRIMARY KEY (id),
  UNIQUE KEY name (name),
  KEY active (active),
  KEY name_2 (name,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_role (
  id int(11) NOT NULL AUTO_INCREMENT,
  cat int(11) NOT NULL DEFAULT '0',
  name varchar(68) NOT NULL DEFAULT 'None',
  comment text,
  active char(2) DEFAULT 'on',
  PRIMARY KEY (id),
  UNIQUE KEY name (name),
  KEY name_2 (name,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_roleactivities (
  role int(11) NOT NULL DEFAULT '0',
  activity int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (role,activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_rolecat (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(68) NOT NULL DEFAULT 'None',
  active char(2) DEFAULT 'on',
  PRIMARY KEY (id),
  UNIQUE KEY name (name),
  KEY name_2 (name,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_session (
  id varchar(255) NOT NULL,
  ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ts (ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE sio_sessiondata (
  sid varchar(200) NOT NULL,
  name varchar(128) NOT NULL,
  value longtext NOT NULL,
  session varchar(200) DEFAULT NULL,
  ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (sid,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

