import{_ as h}from"./Authenticated.7a610d8b.js";import{_ as D}from"./Graph.9d0805cc.js";import{h as o,K as x,U as k,f as S,a as n,u as w,w as u,F as B,o as f,Z as O,b as l,c as N}from"./app.6b3344a8.js";import"./open-closed.99a71fa1.js";import"./use-resolve-button-type.b4c8ca14.js";import"./RectangleStackIcon.e9091161.js";const C=l("div",{class:"flex flex-col space-y-1"},[l("div",{class:"flex space-x-2 items-center"}," Dashboard ")],-1),G={class:"py-12"},J={class:"max-w-7xl mx-auto sm:px-3 lg:px-2"},$={class:"bg-white overflow-hidden shadow-sm sm:rounded-lg"},A={class:"p-1 bg-white border-b border-gray-200"},M={__name:"Dashboard",props:{dayGraphData:Object},setup(y){const p=y;o({day_date_from:"",day_date_to:""});const b=o(0),m=o([]),c=o([]),d=o([]),v=x().props.auth.operator,g=o({scales:{x:{ticks:{min:1,max:31,stepSize:1}},y:{position:"left",title:{display:!0,text:"Sales($)"}},y1:{position:"right",title:{display:!0,text:"Sales(#)"}}},plugins:{title:{display:!0,text:"Sales by Days ("+v.name+")"}}});k(()=>{m.value=JSON.parse(JSON.stringify(p.dayGraphData));let t=[],e=["#3e95cd","#ff7f7f","#3cba9f","#c45850","#c45850"];t=_.groupBy(JSON.parse(JSON.stringify(p.dayGraphData)).data,"month_name"),Object.keys(t).forEach((s,a)=>{c.value.push({label:s+" ($)",data:t[s].map(i=>i.amount),backgroundColor:a%2==0?r(e[a],.4):r(e[a],.9),borderColor:e[a],fill:!1,yAxisID:"y",type:"bar",order:2}),c.value.push({label:s+" (#)",data:t[s].map(i=>i.count),backgroundColor:a%2==0?r(e[a+2],.4):r(e[a+2],.9),borderColor:a%2==0?r(e[a+2],.4):r(e[a+2],.9),yAxisID:"y1",type:"line",order:1})});for(let s=1;s<=31;s++)d.value.push(s)});function r(t,e){var s=parseInt(t.slice(1,3),16),a=parseInt(t.slice(3,5),16),i=parseInt(t.slice(5,7),16);return"rgba("+s+", "+a+", "+i+", "+e+")"}return(t,e)=>(f(),S(B,null,[n(w(O),{title:"Dashboard"}),n(h,null,{header:u(()=>[C]),default:u(()=>[l("div",G,[l("div",J,[l("div",$,[l("div",A,[(f(),N(D,{key:b.value,type:"scatter",labels:d.value,datasets:c.value,options:g.value},null,8,["labels","datasets","options"]))])])])])]),_:1})],64))}};export{M as default};
