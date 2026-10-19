/**
 * Doogsan story editor (TipTap). Stores a JSON document; the server
 * renders sanitized HTML from it (App\Services\BodyRenderer), so editors
 * never write raw HTML.
 */
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Youtube from '@tiptap/extension-youtube';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';

const MediaImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      mediaId: { default: null, parseHTML: (el) => el.getAttribute('data-media-id'), renderHTML: (a) => (a.mediaId ? { 'data-media-id': a.mediaId } : {}) },
      caption: { default: null, parseHTML: (el) => el.getAttribute('data-caption'), renderHTML: (a) => (a.caption ? { 'data-caption': a.caption } : {}) },
    };
  },
});

const PullQuote = Node.create({
  name: 'pullQuote',
  group: 'block',
  content: 'paragraph+',
  defining: true,
  addAttributes() { return { cite: { default: null } }; },
  parseHTML() { return [{ tag: 'figure.pull-quote' }]; },
  renderHTML({ HTMLAttributes }) { return ['figure', mergeAttributes(HTMLAttributes, { class: 'pull-quote' }), 0]; },
});

const FactBox = Node.create({
  name: 'factBox',
  group: 'block',
  content: 'block+',
  defining: true,
  addAttributes() { return { title: { default: null } }; },
  parseHTML() { return [{ tag: 'aside.fact-box' }]; },
  renderHTML({ HTMLAttributes }) { return ['aside', mergeAttributes(HTMLAttributes, { class: 'fact-box' }), 0]; },
});

const RelatedStory = Node.create({
  name: 'relatedStory',
  group: 'block',
  atom: true,
  addAttributes() { return { articleId: { default: null }, title: { default: '' } }; },
  parseHTML() { return [{ tag: 'div.related-block' }]; },
  renderHTML({ node }) { return ['div', { class: 'related-block' }, `Related story: ${node.attrs.title || '#' + node.attrs.articleId}`]; },
});

const Embed = Node.create({
  name: 'embed',
  group: 'block',
  atom: true,
  addAttributes() { return { url: { default: '' }, title: { default: '' }, caption: { default: '' }, height: { default: 480 } }; },
  parseHTML() { return [{ tag: 'div.embed-block' }]; },
  renderHTML({ node }) { return ['div', { class: 'embed-block' }, `Embed: ${node.attrs.title || node.attrs.url}`]; },
});

export function createEditor(element, content, onUpdate, placeholder) {
  return new Editor({
    element,
    content: content && content.type === 'doc' ? content : '',
    extensions: [
      StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: false }),
      Link.configure({ openOnClick: false, autolink: true, protocols: ['http', 'https', 'mailto'] }),
      MediaImage.configure({ inline: false }),
      Placeholder.configure({ placeholder: placeholder || 'Write the story…' }),
      Youtube.configure({ nocookie: true, controls: true, width: 640, height: 360 }),
      Table.configure({ resizable: false }), TableRow, TableHeader, TableCell,
      PullQuote, FactBox, RelatedStory, Embed,
    ],
    editorProps: { attributes: { class: 'editor-surface', spellcheck: 'true' } },
    onUpdate: ({ editor }) => onUpdate?.(editor.getJSON()),
  });
}

export function wordCount(json) {
  let text = '';
  const walk = (n) => { if (n.text) text += ' ' + n.text; (n.content || []).forEach(walk); };
  if (json) walk(json);
  const words = text.trim() ? text.trim().split(/\s+/).length : 0;
  return { words, minutes: Math.max(1, Math.ceil(words / 220)) };
}
