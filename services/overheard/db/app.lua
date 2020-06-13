#!/usr/bin/env tarantool
-- Configure database

clock = require('clock')
uuid = require('uuid')

key = uuid.str()

box.cfg {
   listen = 3301
}

box.once("bootstrap", function()
   s=box.schema.space.create('users')
   s:create_index('primary', { type = 'TREE', parts = {{field = 1, type = 'string'}}})
   box.schema.sequence.create('postId', {min=1, start=1})
   s=box.schema.space.create('posts');
   s:create_index('primary', { type = 'TREE', sequence='postId', parts = {{field = 1, type = 'unsigned'}}})
   s:create_index('user', { type = 'TREE', unique=false, parts = {{field = 2, type = 'string'}}})
   s:create_index('draft',{ type = 'TREE', unique=false, parts = {{field = 4, type = 'boolean'}}})
   box.schema.func.create('keygen.GenerateToken', {language = 'C'})
   box.schema.user.grant('guest', 'execute', 'function', 'keygen.GenerateToken')
   box.schema.func.create('keygen.GetByToken', {language = 'C'})
   box.schema.user.grant('guest', 'execute', 'function', 'keygen.GetByToken')
end)

net_box = require('net.box')
capi_connection = net_box:new(3301)


function addUser(login, password)
    u = box.tuple.new({login, password})
    return box.space.users:insert(u)[1]
end

function findUser(login, password)
    u = box.space.users:select(login)[1]
    if u == nil then return nil end
    if (password ~= nil) then if password == u[2] then return u[1] else return nil end else return u[1] end
    return nil
end

function addPost(user_id, text, is_draft)
    p = box.tuple.new({nil, user_id, text, is_draft, clock.time()})
    return box.space.posts:insert(p)[1]
end

function userPosts(user_id, paginator)
    return box.space.posts.index.user:select(user_id, paginator)
end

function latestPosts(paginator)
    paginator['iterator'] = 'REQ'
    posts = box.space.posts.index.draft:select(false, paginator)
    return posts
end

function findPost(post_id)
    return box.space.posts:select(post_id)[1]
end

function updatePost(post_id, user_id, text, is_draft)
    p = box.tuple.new({post_id, user_id, text, is_draft, clock.time()})
    return box.space.posts:replace(p)[1]
end


function generateToken(post_id)
    if (type(post_id) ~= 'number') then return nil end
    return capi_connection:call('keygen.GenerateToken', {post_id, key})[1]
end

function getByToken(token)
    if (type(token) ~= 'string') then return nil end
    post_id = capi_connection:call('keygen.GetByToken', {token, key})[1]
    if (post_id == nil) then return nil end
    return findPost(post_id)
end

