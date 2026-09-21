@php use App\Support\Latex; $e = fn($v) => Latex::escape($v); @endphp
\documentclass[9pt,a4paper]{article}
\usepackage[a4paper,margin=11mm]{geometry}
\usepackage{fontspec,xcolor,graphicx,hyperref,tikz,array,paracol}
\definecolor{accent}{HTML}{@php echo strtoupper(ltrim($cv['accent'], '#')); @endphp}
\definecolor{muted}{HTML}{52605A}
\setmainfont{Fira Sans}[Path=resources/fonts/,Extension=.ttf,UprightFont=FiraSans-Regular,BoldFont=FiraSans-Bold]
\hypersetup{colorlinks=true,urlcolor=accent,linkcolor=accent,pdfauthor={ {{ $e($cv['name']) }} },pdftitle={Lebenslauf {{ $e($cv['name']) }}}}
\pagestyle{empty}\setlength{\parindent}{0pt}
\newcommand{\sectiontitle}[1]{\vspace{2mm}{\fontsize{15}{17}\selectfont\bfseries #1}\par\vspace{1mm}\color{accent}\rule{\linewidth}{0.7pt}\color{black}\par}
\newcommand{\entry}[5]{\begin{minipage}{\linewidth}\vspace{1.7mm}\begin{tabular}{@{}p{.68\linewidth}>{\raggedleft\arraybackslash}p{.29\linewidth}@{}}\textbf{\href{#4}{#1}} & \small\color{muted}#2\\[-.2mm]\small\textbf{#3} & \\\end{tabular}\par\small\color{muted}\vspace{.7mm}#5\par\end{minipage}\vspace{.8mm}}
\begin{document}
\begin{tikzpicture}[remember picture,overlay]
\fill[accent!18] (current page.north west) rectangle ([yshift=-49mm]current page.north east);
\fill[accent!48] ([xshift=128mm,yshift=-49mm]current page.north west) -- ([xshift=168mm]current page.north west) -- (current page.north east) -- ([yshift=-49mm]current page.north east) -- cycle;
\end{tikzpicture}
\begin{minipage}[b][42mm][b]{.72\linewidth}
@if($cv['profile'])
\makebox[0pt][l]{\raisebox{10mm}{\small\color{accent}\textbf{ {{ $e($cv['profile']['recipient']) }} }
@if($cv['profile']['year'])
 · {{ $e($cv['profile']['year']) }}
@endif
}}\color{black}
@endif
{\fontsize{34}{35}\selectfont\bfseries {{ $e($cv['name']) }}}\par\vspace{2mm}\small\textbf{ {{ $e($cv['location']) }} }
\end{minipage}\hfill
@if($cv['photo'])
\begin{minipage}[b][42mm][b]{.22\linewidth}\raggedleft\includegraphics[width=35mm,height=35mm,keepaspectratio]{ {{ $e($cv['photo']) }} }\end{minipage}
@endif
\vspace{6mm}
\columnratio{0.22}
\setlength{\columnsep}{8mm}
\begin{paracol}{2}
{\bfseries\small KONTAKT}\par\vspace{2mm}\footnotesize
@if($cv['contact']['email'])
\href{mailto:{{ $e($cv['contact']['email']) }}}{ {{ $e($cv['contact']['email']) }} }\par
@endif
@if($cv['contact']['phone'])
{{ $e($cv['contact']['phone']) }}\par
@endif
@if($cv['contact']['address'])
{{ $e($cv['contact']['address']) }}\par
@endif
{{ $e($cv['location']) }}\par\vspace{4mm}
@if($cv['about'])
{\bfseries\small ÜBER MICH}\par\vspace{1.5mm}{{ $e($cv['about']) }}\par\vspace{4mm}
@endif
@foreach($cv['knowledge'] as $group)
{\bfseries\small {{ mb_strtoupper($e($group['category'] ?? '')) }}}\par
@foreach(($group['items'] ?? []) as $item)
{{ $e($item['label'] ?? '') }}
@if(!empty($item['context']))
\newline{\color{muted}\scriptsize {{ $e($item['context']) }}}
@endif
\par\vspace{.8mm}
@endforeach
\vspace{2mm}
@endforeach
@if($cv['soft_skills'])
{\bfseries\small SOFT SKILLS}\par\vspace{1.5mm}
@foreach($cv['soft_skills'] as $skill)
\textbf{ {{ $e($skill['label'] ?? '') }} }
@if(!empty($skill['context']))
\newline{\color{muted}\scriptsize {{ $e($skill['context']) }}}
@endif
\par\vspace{1mm}
@endforeach
@endif
\vspace{3mm}{\bfseries\small ONLINE}\par\vspace{1.5mm}\href{ {{ $cv['contact']['website'] }} }{Website}\par\href{ {{ $cv['contact']['github'] }} }{GitHub}\par\href{ {{ $cv['contact']['codeberg'] }} }{Codeberg}
\switchcolumn
\sectiontitle{Erfahrung}
@foreach($cv['experience'] as $item)
\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['organisation']) }} }{ {{ $item['url'] }} }{ {{ $e($item['summary']) }} }
@endforeach
\sectiontitle{Bildung}
@foreach($cv['education'] as $item)
\entry{ {{ $e($item['programme'] ?: $item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['title']) }} }{ {{ $item['url'] }} }{ {{ $e($item['summary']) }} }
@endforeach
\sectiontitle{Projekte}
@foreach($cv['projects'] as $item)
\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{}{ {{ $item['url'] }} }{ {{ $e($item['summary']) }} }
@endforeach
\sectiontitle{Veröffentlichungen}
@foreach($cv['publications'] as $item)
\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['type']) }} }{ {{ $item['url'] }} }{}
@endforeach
\vspace{4mm}\colorbox{accent!16}{\parbox{.94\linewidth}{\small Mehr Details und die interaktive Version dieses Lebenslaufs: \href{ {{ $cv['canonical_url'] }} }{ {{ $e($cv['canonical_url']) }} }}}
\end{paracol}
\end{document}
