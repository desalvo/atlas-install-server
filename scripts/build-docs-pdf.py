#!/usr/bin/env python3
"""Regenerate the detailed LJSF 3 manuals and REST API PDFs from Markdown.

Requires pandoc and weasyprint. Source files live under docs/; generated PDFs are
copied both to docs/ and to the Web application's docs directory.
"""
from pathlib import Path
import subprocess, shutil, tempfile
ROOT=Path(__file__).resolve().parents[1]
DOC=ROOT/'docs'
WEB=ROOT/'var/www/html/atlas_install-3.0.0/docs'
WEB.mkdir(parents=True,exist_ok=True)
CSS=DOC/'manual.css'
for lang in ('it','en'):
    pairs=[(DOC/f'USER-GUIDE.{lang}.md', DOC/f'LJSF3-Manual.{lang}.pdf'),
           (DOC/f'REST-API.{lang}.md', DOC/f'LJSF3-REST-API.{lang}.pdf')]
    for src,out in pairs:
        with tempfile.NamedTemporaryFile(suffix='.html',delete=False,dir=DOC) as tf:
            html=Path(tf.name)
        try:
            subprocess.run(['pandoc',str(src),'--standalone','--css',CSS.name,'-o',str(html)],cwd=DOC,check=True)
            subprocess.run(['weasyprint',str(html),str(out)],cwd=DOC,check=True)
        finally:
            html.unlink(missing_ok=True)
        shutil.copy2(out,WEB/out.name)
        html_out = WEB / (out.stem + '.html')
        subprocess.run(['pandoc',str(src),'--standalone','--css','manual.css','-o',str(html_out)],cwd=DOC,check=True)
print('Detailed PDF manuals regenerated successfully.')
