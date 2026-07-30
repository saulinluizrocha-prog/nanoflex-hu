const crypto = require('crypto');
const https = require('https');
const querystring = require('querystring');

const CONFIG = {
    api_key: 'c66289394c2a6e8515c8e8b382fba719',
    offer_id: 14858,
    user_id: 75329,
    stream_id: '409913',
    country: 'HU',
    tz: 2,
    api_domain: 'https://t-api.org'
};

function normalizePhone(phone) {
    if (!phone) return '';
    let cleaned = phone.replace(/[^0-9+]/g, '');
    if (cleaned.startsWith('+36')) {
        // already +36
    } else if (cleaned.startsWith('0036')) {
        cleaned = '+36' + cleaned.slice(4);
    } else if (cleaned.startsWith('36') && cleaned.length >= 10) {
        cleaned = '+' + cleaned;
    } else if (cleaned.startsWith('06')) {
        cleaned = '+36' + cleaned.slice(2);
    } else if (cleaned.startsWith('0')) {
        cleaned = '+36' + cleaned.slice(1);
    } else if (!cleaned.startsWith('+')) {
        cleaned = '+36' + cleaned;
    }
    return cleaned;
}

function parseBody(req) {
    return new Promise((resolve) => {
        if (req.body && typeof req.body === 'object') {
            return resolve(req.body);
        }
        let bodyStr = '';
        req.on('data', chunk => {
            bodyStr += chunk.toString();
        });
        req.on('end', () => {
            try {
                if (req.headers['content-type'] && req.headers['content-type'].includes('application/json')) {
                    resolve(JSON.parse(bodyStr || '{}'));
                } else {
                    resolve(querystring.parse(bodyStr || ''));
                }
            } catch (e) {
                resolve({});
            }
        });
    });
}

function getClientIp(req) {
    const cfIp = req.headers['cf-connecting-ip'];
    if (cfIp) return cfIp;

    const xForwardedFor = req.headers['x-forwarded-for'];
    if (xForwardedFor) {
        return xForwardedFor.split(',')[0].trim();
    }

    const realIp = req.headers['x-real-ip'];
    if (realIp) return realIp;

    return req.socket ? req.socket.remoteAddress : '127.0.0.1';
}

function postToTerra(payload) {
    return new Promise((resolve, reject) => {
        const jsonData = JSON.stringify(payload);
        const checksum = crypto.createHash('sha1').update(jsonData + CONFIG.api_key).digest('hex');
        const url = `${CONFIG.api_domain}/api/lead/create?check_sum=${checksum}`;

        const urlObj = new URL(url);
        const options = {
            hostname: urlObj.hostname,
            path: urlObj.pathname + urlObj.search,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(jsonData)
            },
            timeout: 10000
        };

        const request = https.request(options, (response) => {
            let resBody = '';
            response.on('data', chunk => { resBody += chunk; });
            response.on('end', () => {
                try {
                    const parsed = JSON.parse(resBody);
                    resolve(parsed);
                } catch (err) {
                    reject(new Error('Invalid JSON from API: ' + resBody));
                }
            });
        });

        request.on('error', (err) => reject(err));
        request.on('timeout', () => {
            request.destroy();
            reject(new Error('API Request Timeout'));
        });

        request.write(jsonData);
        request.end();
    });
}

module.exports = async function handler(req, res) {
    if (req.method !== 'POST' && req.method !== 'GET') {
        res.setHeader('Allow', ['POST', 'GET']);
        return res.status(405).json({ error: 'Method Not Allowed' });
    }

    if (req.method === 'GET') {
        return res.status(200).send('API endpoint operational.');
    }

    try {
        const postData = await parseBody(req);
        const queryData = req.query || {};

        const name = (postData.name || queryData.name || '').trim();
        const rawPhone = (postData.phone || queryData.phone || '').trim();
        const phone = normalizePhone(rawPhone);

        const ip = getClientIp(req);
        const userAgent = req.headers['user-agent'] || '';
        const referer = req.headers['referer'] || '';

        const leadParams = {
            name: name,
            phone: phone,
            offer_id: CONFIG.offer_id,
            country: postData.country || CONFIG.country,
            tz: CONFIG.tz,
            stream_id: CONFIG.stream_id,
            ip: ip,
            user_agent: userAgent,
            referer: referer,
            region: postData.region || null,
            city: postData.city || null,
            address: postData.address || null,
            email: postData.email || null,
            zip: postData.zip || null,
            user_comment: postData.user_comment || null,
            utm_source: postData.utm_source || queryData.utm_source || null,
            utm_medium: postData.utm_medium || queryData.utm_medium || null,
            utm_campaign: postData.utm_campaign || queryData.utm_campaign || null,
            utm_term: postData.utm_term || queryData.utm_term || null,
            utm_content: postData.utm_content || queryData.utm_content || null,
            sub_id: postData.sub_id || queryData.sub_id || null,
            sub_id_1: postData.sub_id_1 || queryData.sub_id_1 || null,
            sub_id_2: postData.sub_id_2 || queryData.sub_id_2 || null,
            sub_id_3: postData.sub_id_3 || queryData.sub_id_3 || null,
            sub_id_4: postData.sub_id_4 || queryData.sub_id_4 || null
        };

        const terraPayload = {
            user_id: CONFIG.user_id,
            data: leadParams
        };

        const apiResult = await postToTerra(terraPayload);

        let redirectTarget = queryData.redirect || 'success.html';
        if (req.url && req.url.includes('hu-send')) {
            redirectTarget = 'hu-success.html';
        }

        if (apiResult && apiResult.status === 'ok' && apiResult.data) {
            const leadId = apiResult.data.id || apiResult.data.lead_id || '';
            const targetUrl = `/${redirectTarget}?id=${encodeURIComponent(leadId)}`;
            res.writeHead(302, { Location: targetUrl });
            return res.end();
        } else {
            const errorMsg = (apiResult && apiResult.error) ? apiResult.error : 'Unknown API error';
            console.error('Terra API Error:', errorMsg);
            // Fallback redirect to success page so lead isn't lost for user experience
            res.writeHead(302, { Location: `/${redirectTarget}?status=submitted` });
            return res.end();
        }
    } catch (err) {
        console.error('Handler Error:', err);
        let redirectTarget = req.query && req.query.redirect ? req.query.redirect : 'success.html';
        res.writeHead(302, { Location: `/${redirectTarget}?status=submitted` });
        return res.end();
    }
};
