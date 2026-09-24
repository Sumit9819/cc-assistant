      // ---------------------------------------------------------------------
      // Markdown rendering.
      //
      // Everything is built with createElement/textContent, so the strict CSP
      // and the no-innerHTML rule still hold: assistant output can never become
      // markup. The highlighter is deliberately generic rather than
      // language-accurate; bundling real grammars is not worth the weight here.
      //
      // BACKTICK is spelled as an escape because this script is embedded through
      // String.raw, which would otherwise keep the backslash before a literal
      // backtick.
      // ---------------------------------------------------------------------
      const BACKTICK = '`';

      const KEYWORDS = new Set(('abstract,as,async,await,base,bool,break,case,catch,char,class,const,constexpr,continue,' +
        'debugger,def,default,del,delete,do,double,elif,else,elseif,end,enum,event,except,export,extends,extern,final,' +
        'finally,float,fn,for,from,func,function,global,go,goto,if,impl,implements,import,in,instanceof,int,interface,' +
        'is,lambda,let,local,long,match,mod,module,mut,namespace,new,nil,none,not,null,operator,or,package,params,pass,' +
        'private,protected,pub,public,raise,readonly,ref,require,return,select,self,short,sizeof,static,struct,super,' +
        'switch,template,then,this,throw,throws,trait,try,type,typedef,typeof,union,unless,unsigned,until,use,using,' +
        'var,virtual,void,volatile,when,where,while,with,yield,true,false,True,False,None,undefined,and').split(','));

      const TOKEN_PATTERN = new RegExp([
        '(/\\*[\\s\\S]*?\\*/|//[^\\n]*|#[^\\n]*|--[^\\n]*)',
        '("(?:[^"\\\\\\n]|\\\\.)*"' +
          "|'(?:[^'\\\\\\n]|\\\\.)*'" +
          '|' + BACKTICK + '(?:[^' + BACKTICK + '\\\\]|\\\\.)*' + BACKTICK + ')',
        '(\\b0[xXbBoO][0-9a-fA-F_]+\\b|\\b\\d[\\d_]*(?:\\.\\d+)?(?:[eE][+-]?\\d+)?\\b)',
        '([A-Za-z_$][\\w$]*)'
      ].join('|'), 'g');

      function highlightInto(parent, code) {
        let lastIndex = 0;
        let match;
        TOKEN_PATTERN.lastIndex = 0;
        while ((match = TOKEN_PATTERN.exec(code)) !== null) {
          if (match.index > lastIndex) {
            parent.appendChild(document.createTextNode(code.slice(lastIndex, match.index)));
          }
          let className = null;
          if (match[1]) className = 'tok-comment';
          else if (match[2]) className = 'tok-string';
          else if (match[3]) className = 'tok-number';
          else if (match[4] && KEYWORDS.has(match[4])) className = 'tok-keyword';

          if (className) {
            const span = document.createElement('span');
            span.className = className;
            span.textContent = match[0];
            parent.appendChild(span);
          } else {
            parent.appendChild(document.createTextNode(match[0]));
          }
          lastIndex = match.index + match[0].length;
        }
        if (lastIndex < code.length) {
          parent.appendChild(document.createTextNode(code.slice(lastIndex)));
        }
      }

      function copyText(text, button, restoreLabel) {
        const done = function (label) {
          button.textContent = label;
          setTimeout(function () { button.textContent = restoreLabel; }, 1200);
        };
        try {
          const result = navigator.clipboard.writeText(text);
          if (result && typeof result.then === 'function') {
            result.then(function () { done('Copied'); }, function () { done('Failed'); });
          } else {
            done('Copied');
          }
        } catch (error) {
          done('Failed');
        }
      }

      function createCodeBlock(language, code) {
        const block = document.createElement('div');
        block.className = 'code-block';

        const header = document.createElement('div');
        header.className = 'code-header';
        const languageLabel = document.createElement('span');
        languageLabel.className = 'code-language';
        languageLabel.textContent = language || 'code';
        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'code-copy';
        copy.textContent = 'Copy';
        copy.addEventListener('click', function () { copyText(code, copy, 'Copy'); });
        header.append(languageLabel, copy);

        const pre = document.createElement('pre');
        pre.className = 'code-body';
        const codeElement = document.createElement('code');
        highlightInto(codeElement, code);
        pre.appendChild(codeElement);

        block.append(header, pre);
        return block;
      }

      // Inline spans: code first, so a code span is never reinterpreted as
      // emphasis, then bold, italic, and links.
      const INLINE_PATTERN = new RegExp(
        '(' + BACKTICK + '+)([\\s\\S]*?)\\1' +
        '|\\*\\*([^*]+)\\*\\*' +
        '|__([^_]+)__' +
        '|(?<![\\w*])\\*([^*\\n]+)\\*(?!\\w)' +
        '|(?<![\\w_])_([^_\\n]+)_(?!\\w)' +
        '|\\[([^\\]]+)\\]\\(([^)\\s]+)\\)',
        'g'
      );

      function appendInline(parent, text) {
        let lastIndex = 0;
        let match;
        INLINE_PATTERN.lastIndex = 0;
        while ((match = INLINE_PATTERN.exec(text)) !== null) {
          if (match.index > lastIndex) {
            parent.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
          }
          if (match[2] !== undefined) {
            const code = document.createElement('code');
            code.className = 'inline-code';
            code.textContent = match[2].trim();
            parent.appendChild(code);
          } else if (match[3] !== undefined || match[4] !== undefined) {
            const strong = document.createElement('strong');
            strong.textContent = match[3] !== undefined ? match[3] : match[4];
            parent.appendChild(strong);
          } else if (match[5] !== undefined || match[6] !== undefined) {
            const em = document.createElement('em');
            em.textContent = match[5] !== undefined ? match[5] : match[6];
            parent.appendChild(em);
          } else if (match[7] !== undefined) {
            if (/^https?:\/\//i.test(match[8])) {
              const anchor = document.createElement('a');
              anchor.href = match[8];
              anchor.textContent = match[7];
              parent.appendChild(anchor);
            } else {
              parent.appendChild(document.createTextNode(match[0]));
            }
          }
          lastIndex = match.index + match[0].length;
        }
        if (lastIndex < text.length) {
          parent.appendChild(document.createTextNode(text.slice(lastIndex)));
        }
      }

      const FENCE_PATTERN = new RegExp('^\\s*(' + BACKTICK + '{3,}|~{3,})\\s*([\\w+#.-]*)\\s*$');

      function flushList(target, list) {
        if (list.items.length === 0) return;
        const element = document.createElement(list.ordered ? 'ol' : 'ul');
        list.items.forEach(function (item) {
          const li = document.createElement('li');
          appendInline(li, item);
          element.appendChild(li);
        });
        target.appendChild(element);
        list.items = [];
      }

      function flushParagraph(target, buffer) {
        if (buffer.length === 0) return;
        const paragraph = document.createElement('p');
        appendInline(paragraph, buffer.join('\n'));
        target.appendChild(paragraph);
        buffer.length = 0;
      }

      function renderMarkdown(target, source) {
        target.replaceChildren();
        const lines = String(source == null ? '' : source).split('\n');
        const paragraph = [];
        const list = { ordered: false, items: [] };
        let index = 0;

        while (index < lines.length) {
          const line = lines[index];
          const fence = FENCE_PATTERN.exec(line);
          if (fence) {
            flushParagraph(target, paragraph);
            flushList(target, list);
            const closing = new RegExp('^\\s*' + fence[1][0] + '{3,}\\s*$');
            const body = [];
            index += 1;
            while (index < lines.length && !closing.test(lines[index])) {
              body.push(lines[index]);
              index += 1;
            }
            index += 1;
            target.appendChild(createCodeBlock(fence[2], body.join('\n')));
            continue;
          }

          const heading = /^(#{1,6})\s+(.*)$/.exec(line);
          if (heading) {
            flushParagraph(target, paragraph);
            flushList(target, list);
            const element = document.createElement('h' + Math.min(6, heading[1].length + 2));
            appendInline(element, heading[2].trim());
            target.appendChild(element);
            index += 1;
            continue;
          }

          if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
            flushParagraph(target, paragraph);
            flushList(target, list);
            target.appendChild(document.createElement('hr'));
            index += 1;
            continue;
          }

          const quote = /^\s*>\s?(.*)$/.exec(line);
          if (quote) {
            flushParagraph(target, paragraph);
            flushList(target, list);
            const block = document.createElement('blockquote');
            appendInline(block, quote[1]);
            target.appendChild(block);
            index += 1;
            continue;
          }

          const bullet = /^\s*[-*+]\s+(.*)$/.exec(line);
          const numbered = /^\s*\d+[.)]\s+(.*)$/.exec(line);
          if (bullet || numbered) {
            flushParagraph(target, paragraph);
            const ordered = Boolean(numbered);
            if (list.items.length > 0 && list.ordered !== ordered) {
              flushList(target, list);
            }
            list.ordered = ordered;
            list.items.push((bullet ? bullet[1] : numbered[1]).trim());
            index += 1;
            continue;
          }

          if (!line.trim()) {
            flushParagraph(target, paragraph);
            flushList(target, list);
            index += 1;
            continue;
          }

          flushList(target, list);
          paragraph.push(line);
          index += 1;
        }

        flushParagraph(target, paragraph);
        flushList(target, list);
      }

      // Rendering is coalesced into an animation frame so a fast token stream
      // does not re-parse the whole message on every delta.
      function scheduleRender(entry) {
        if (entry.pending) return;
        entry.pending = true;
        requestAnimationFrame(function () {
          entry.pending = false;
          if (entry.markdown) {
            renderMarkdown(entry.content, entry.raw);
          } else {
            entry.content.textContent = entry.raw;
          }
          scrollToBottom();
        });
      }

      function setContent(entry, text) {
        if (!entry) return;
        entry.raw = text || '';
        scheduleRender(entry);
      }

      function appendContent(entry, text) {
        if (!entry || !text) return;
        entry.raw += text;
        scheduleRender(entry);
      }
