"""Use Claude's normal MCP OAuth flow; never read or replay credential files."""
import json,os,subprocess
from pathlib import Path
root=Path('D:/cc-assistant/reports/preflight-0.89.0-2026-09-09/ubersuggest-client-check');root.mkdir(parents=True,exist_ok=True)
spec=json.loads(Path('C:/Users/sumit/.claude.json').read_text(encoding='utf-8'))['mcpServers']['ubersuggest']
spec=dict(spec,alwaysLoad=True)
(root/'mcp.json').write_text(json.dumps({'mcpServers':{'ubersuggest':spec}}),encoding='utf-8')
(root/'settings.json').write_text(json.dumps({'disableAllHooks':True,'autoMemoryEnabled':False}),encoding='utf-8')
cmd=['C:/Users/sumit/AppData/Roaming/npm/node_modules/@anthropic-ai/claude-code/bin/claude.exe','--print','--output-format','json','--no-session-persistence','--disable-slash-commands','--setting-sources','','--settings',str(root/'settings.json'),'--strict-mcp-config','--mcp-config',str(root/'mcp.json'),'--tools','','--allowedTools','mcp__ubersuggest__connection_check_no_calls','--max-budget-usd','0.25','--debug-file',str(root/'debug.txt'),'--system-prompt','This is a connection/catalog check, not SEO research. Do not call any tools or mutate anything. Return JSON with connected (boolean), actual visible Ubersuggest tool names (array), and unavailable_reason. List only tools actually present in your provided tool catalog.','Check the configured Ubersuggest MCP tool catalog only.']
env=os.environ.copy();env.update(ENABLE_TOOL_SEARCH='false',MCP_CONNECTION_NONBLOCKING='0')
r=subprocess.run(cmd,cwd=root,env=env,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=120)
(root/'cli-output.json').write_text(r.stdout,encoding='utf-8');(root/'stderr.txt').write_text(r.stderr,encoding='utf-8')
try:
 result=json.loads(r.stdout);print(json.dumps({'exit_code':r.returncode,'is_error':result.get('is_error'),'result':result.get('result'),'cost_usd':result.get('total_cost_usd')}))
except ValueError:print(json.dumps({'exit_code':r.returncode,'json_output':False}))
