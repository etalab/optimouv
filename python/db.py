import sys
import config
import pymysql as mdb
import logging

class __db:
	cnx = None
	csr = None
	uncommited = 0

def connect():
	if __db.cnx is None:
		__db.cnx = mdb.connect(config.DB.Host, config.DB.User, config.DB.Password, config.DB.Db  )
		__db.csr = __db.cnx.cursor()
		__db.uncommited = 0

def disconnect():
	if __db.cnx:
		rollback()
		__db.cnx.close()
		__db.csr = None
		__db.cnx = None

def fetch(sql, params = None, min = None, max = None):
	connect()
	if params is None:
		__db.csr.execute(sql)
	else:
		__db.csr.execute(sql, params)
	if min is not None and __db.csr.rowcount < min:
		raise Exception("Not enough rows to fetch")
	if max is not None and __db.csr.rowcount > max:
		raise Exception("Too many rows to fetch")
	return __db.csr.fetchall()

"""
Fetch several values for one variable
"""
def fetchone_column(sql, params = None, min = None, max = None):
	connect()
	if params is None:
		__db.csr.execute(sql)
	else:
		__db.csr.execute(sql, params)
	if min is not None and __db.csr.rowcount < min:
		raise Exception("Not enough rows to fetch")
	if max is not None and __db.csr.rowcount > max:
		raise Exception("Too many rows to fetch")
	
	result = [ el[0] for el in __db.csr.fetchall() ]
	return result

"""
Fetch one value for one variable
"""
def fetchone(sql, params = None, required = False):
	res = fetch(sql, params, min=1 if required else 0)
	
	return res[0][0] if len(res) == 1 else None

"""
Fetch one value for multiple variables
"""
def fetchone_multi(sql, params = None, required = False):
	res = fetch(sql, params, min=1 if required else 0)
	
	return res[0] if len(res) == 1 else None

def execute(sql, params = None):
	connect()
	status = None
	if params is None:
		status = __db.csr.execute(sql)
	else:
		status = __db.csr.execute(sql, params)
	__db.uncommited = __db.uncommited + 1
	return status, __db.csr

def executemany(sql, params = None):
	connect()
	status = None
	if params is None:
		status = __db.csr.executemany(sql)
	else:
		status = __db.csr.executemany(sql, params)
	__db.uncommited = __db.uncommited + 1
	return status, __db.csr

def commit():
	if __db.uncommited > 0:
		__db.cnx.commit()
		__db.uncommited = 0

def lastinsertedid():
	return __db.csr.lastrowid

def rollback():
	if __db.uncommited > 0:
		__db.cnx.rollback()
		__db.uncommited = 0
