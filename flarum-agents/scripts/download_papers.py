#!/usr/bin/env python3
"""
arXiv论文下载和队列管理脚本
"""
import xml.etree.ElementTree as ET
import json
import os
import subprocess
import sys
from datetime import datetime

def main():
    if len(sys.argv) < 6:
        print("Usage: download_papers.py <xml_file> <daily_dir> <queue_file> <date> <keyword>")
        sys.exit(1)
    
    xml_file = sys.argv[1]
    daily_dir = sys.argv[2]
    queue_file = sys.argv[3]
    date = sys.argv[4]
    keyword = sys.argv[5]
    
    # 命名空间
    NS = {
        'atom': 'http://www.w3.org/2005/Atom',
        'arxiv': 'http://arxiv.org/schemas/atom'
    }
    
    try:
        tree = ET.parse(xml_file)
        root = tree.getroot()
    except Exception as e:
        print(f"解析XML错误: {e}")
        sys.exit(1)
    
    papers = []
    count = 0
    
    for entry in root.findall('atom:entry', NS):
        if count >= 15:
            break
        
        # 提取信息
        id_elem = entry.find('atom:id', NS)
        title_elem = entry.find('atom:title', NS)
        published_elem = entry.find('atom:published', NS)
        summary_elem = entry.find('atom:summary', NS)
        
        if id_elem is None or title_elem is None:
            continue
        
        # 提取arXiv ID
        arxiv_url = id_elem.text
        arxiv_id = arxiv_url.split('/abs/')[-1].split('v')[0]
        
        title = title_elem.text.strip() if title_elem.text else ""
        published = published_elem.text[:10] if published_elem is not None else ""
        abstract = summary_elem.text.strip() if summary_elem is not None and summary_elem.text else ""
        
        # 获取作者
        authors = []
        for author in entry.findall('atom:author', NS):
            name = author.find('atom:name', NS)
            if name is not None:
                authors.append(name.text)
        
        # 构建下载URL
        pdf_url = f"https://arxiv.org/pdf/{arxiv_id}.pdf"
        pdf_file = f"{daily_dir}/{count+1:02d}_{arxiv_id}.pdf"
        
        print(f"[{count+1}/15] 下载论文: {arxiv_id}")
        print(f"    标题: {title[:60]}...")
        
        # 下载PDF
        try:
            result = subprocess.run(
                ['curl', '-s', '-L', '-o', pdf_file, pdf_url],
                capture_output=True,
                timeout=120
            )
            
            if result.returncode == 0 and os.path.exists(pdf_file):
                file_size = os.path.getsize(pdf_file)
                if file_size > 10000:  # 至少10KB才算有效
                    print(f"    ✓ 下载成功 ({file_size} bytes)")
                    
                    paper_info = {
                        "id": arxiv_id,
                        "title": title,
                        "authors": ", ".join(authors[:5]) + (" et al." if len(authors) > 5 else ""),
                        "abstract": abstract,
                        "pdf_url": pdf_url,
                        "abstract_url": arxiv_url,
                        "published": published,
                        "pdf_file": pdf_file,
                        "downloaded_at": datetime.now().isoformat(),
                        "status": "pending",
                        "queue_number": count + 1,
                        "source": "arxiv",
                        "batch_date": date
                    }
                    papers.append(paper_info)
                    count += 1
                else:
                    print(f"    ✗ 文件太小，可能下载失败")
                    if os.path.exists(pdf_file):
                        os.remove(pdf_file)
            else:
                print(f"    ✗ 下载失败")
        except Exception as e:
            print(f"    ✗ 错误: {e}")
    
    # 保存manifest
    manifest = {
        "date": date,
        "keyword": keyword,
        "total_downloaded": len(papers),
        "papers": papers
    }
    
    manifest_file = f"{daily_dir}/manifest.json"
    with open(manifest_file, 'w', encoding='utf-8') as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)
    
    print(f"\n下载完成: {len(papers)} 篇论文已保存到 {daily_dir}")
    
    # 更新队列文件
    existing_queue = []
    if os.path.exists(queue_file):
        try:
            with open(queue_file, 'r', encoding='utf-8') as f:
                existing_queue = json.load(f)
                if not isinstance(existing_queue, list):
                    existing_queue = []
        except:
            existing_queue = []
    
    # 只添加状态为pending的论文
    new_papers = [p for p in papers if p["status"] == "pending"]
    combined_queue = existing_queue + new_papers
    
    # 重新编号
    for i, paper in enumerate(combined_queue, 1):
        paper["queue_number"] = i
    
    with open(queue_file, 'w', encoding='utf-8') as f:
        json.dump(combined_queue, f, ensure_ascii=False, indent=2)
    
    print(f"队列已更新: {len(combined_queue)} 篇论文待解读 ({len(new_papers)} 篇新增)")

if __name__ == '__main__':
    main()
