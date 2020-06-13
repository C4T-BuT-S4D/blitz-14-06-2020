import requests
import random
from checklib import *

PORT = 6091


class CheckMachine:
    @property
    def url(self):
        return f'http://{self.c.host}:{self.port}/api'

    def __init__(self, checker):
        self.c = checker
        self.port = PORT

    def register(self, s, username, password, home):
        url = f'{self.url}/register/'
        r = s.post(url, json={
            'username': username,
            'password': password,
            'home': home,
        })

        self.c.check_response(r, 'Could not register')
        data = self.c.get_json(r, 'Invalid response from register')
        self.c.assert_eq(type(data), type({}), 'Could not register')
        self.c.assert_in('ok', data, 'Could not register')

    def login(self, s, username, password):
        url = f'{self.url}/login/'
        r = s.post(url, json={
            'username': username,
            'password': password,
        })

        self.c.check_response(r, 'Could not login')
        data = self.c.get_json(r, 'Invalid response from login')
        self.c.assert_eq(type(data), type({}), 'Could not login')
        self.c.assert_in('ok', data, 'Could not login')

    def me(self, s, username, home):
        url = f'{self.url}/me/'
        r = s.get(url)

        self.c.check_response(r, 'Could not get me')
        data = self.c.get_json(r, 'Invalid response from me')
        self.c.assert_eq(type(data), type({}), 'Could not list codes')
        self.c.assert_in('ok', data, 'Could not get me')
        self.c.assert_eq(type(data['ok']), type({}), 'Could not get me')
        self.c.assert_in('username', data['ok'], 'Could not get me')
        self.c.assert_in('home', data['ok'], 'Could not get me')
        self.c.assert_eq(username, data['ok']['username'], 'Could not get me')
        self.c.assert_eq(home, data['ok']['home'], 'Could not get me')

    def create_code(self, home, work):
        home = home[:32].zfill(32)
        work = work[:32].zfill(32)

        con = home + work

        seed = []
        rnd = 0
        cnt = 0
        for i in con:
            if cnt % 2 == 0:
                rnd = random.randint(0, 255)
            cnt += 1
            seed += list(bin(ord(i) ^ rnd)[2:].zfill(8))

        for i in range(0, 4):
            res = 0
            for j in range(i, len(con), 4):
                res ^= ord(con[j])
            seed += list(bin(res)[2:].zfill(8))

        return list(map(int, seed))

    def upload_code(self, s, code, work):
        url = f'{self.url}/code/'
        r = s.post(url, json={
            'work': work,
            'code': code,
        })

        self.c.check_response(r, 'Could not upload code')
        data = self.c.get_json(r, 'Invalid response from upload code')
        self.c.assert_eq(type(data), type({}), 'Could not upload code')
        self.c.assert_in('ok', data, 'Could not upload code')
        self.c.assert_eq(type(data['ok']), type(""), 'Could not upload code')

        return data['ok']

    def list_codes(self, s, cid):
        url = f'{self.url}/codes/'
        r = s.get(url)

        self.c.check_response(r, 'Could not list codes')
        data = self.c.get_json(r, 'Invalid response from list codes')
        self.c.assert_eq(type(data), type({}), 'Could not list codes')
        self.c.assert_in('ok', data, 'Could not list codes')
        self.c.assert_eq(type(data['ok']), type([]), 'Could not list codes')
        self.c.assert_in(cid, data['ok'], 'Could not list codes')

    def verify_code(self, s, cid, h, w, status=Status.MUMBLE):
        url = f'{self.url}/code/{cid}/'

        r = s.get(url)

        self.c.check_response(r, 'Could not get code')
        data = self.c.get_json(r, 'Invalid response from get code')
        self.c.assert_eq(type(data), type({}), 'Could not get code')
        self.c.assert_in('ok', data, 'Could not get code')
        self.c.assert_eq(type(data['ok']), type([]), 'Could not get code')
        for i in data['ok']:
            self.c.assert_eq(type(i), type(0), 'Could not get code')

        seed = data['ok']
        self.c.assert_eq(len(seed), 544, 'Invalid qr on get code')

        hh = seed[:256]
        hhh = bytes([int(''.join(map(str, hh[i:i+8])), 2) for i in range(0, len(hh), 8)])

        ww = seed[256:512]
        www = bytes([int(''.join(map(str, ww[i:i+8])), 2) for i in range(0, len(ww), 8)])

        it = seed[512:]
        itt = bytes([int(''.join(map(str, it[i:i+8])), 2) for i in range(0, len(it), 8)])

        r1 = 0
        for i in range(32):
            r1 ^= hhh[i] ^ ord(h[i])
        self.c.assert_eq(r1, 0, 'Could not verify code', status)

        r2 = 0
        for i in range(32):
            r2 ^= www[i] ^ ord(w[i])
        self.c.assert_eq(r2, 0, 'Could not verify code', status)

        con = h + w
        for i in range(4):
            r = 0
            for j in range(i, len(con), 4):
                r ^= ord(con[j])
            self.c.assert_eq(r, itt[i], 'Could not verify code', status)
