import { storageRequest, type StorageEnv } from '../../src/storage';
export default { async fetch(request: Request, env: StorageEnv) { return await storageRequest(request, env) ?? new Response('Not found', {status:404}); } };
