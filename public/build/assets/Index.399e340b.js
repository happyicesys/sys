import{_ as I}from"./Authenticated.7e90e6af.js";import{_ as g}from"./Button.4631b684.js";import{g as c,h as O,f as u,a as t,u as m,w as s,F as v,o as f,Z as S,b as e,d,k as K,l as F,t as b,O as C}from"./app.c4e47028.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dac4e319.js";import{_ as x,a as p}from"./TableData.e3cd56cb.js";import{_ as T}from"./TableHeadSort.6b73435e.js";import{r as V}from"./PlusIcon.fa20410d.js";import{r as j}from"./PencilSquareIcon.ac8e2982.js";import{r as A}from"./TrashIcon.eeea9cb1.js";import"./keyboard.58689cfa.js";import"./use-resolve-button-type.ef81a21b.js";import"./RectangleStackIcon.b91bd271.js";const D=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Oauth & API ",-1),E={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},J={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},M={class:"flex justify-end"},R=e("span",null," Create ",-1),U={class:"mt-6 flex flex-col"},Z={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},q={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},z={class:"min-w-full border-separate",style:{"border-spacing":"0"}},G={class:"bg-gray-100"},H={class:"divide-x divide-gray-200"},Q={class:"bg-white"},W=e("span",null,null,-1),X={class:"flex justify-center space-x-1"},Y=e("span",null," Edit ",-1),ee=e("span",null," Delete ",-1),te={key:0},se=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),ne=[se],xe={__name:"Index",props:{clients:Object},setup(n){const w=n,o=c({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),_=c(!1),h=c(),y=c("");O(()=>{console.log(JSON.parse(JSON.stringify(w.clients)))});function k(){y.value="create",h.value=null,_.value=!0}function $(a){!confirm("Are you sure to delete "+a.name+"?")||C.delete("/clients/"+a.id)}function B(a){y.value="update",h.value=a,_.value=!0}function L(){C.get("/clients",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function N(a){o.value.sortKey=a,o.value.sortBy=!o.value.sortBy,L()}return(a,l)=>(f(),u(v,null,[t(m(S),{title:"Oauth & API"}),t(I,null,{header:s(()=>[D]),default:s(()=>[e("div",E,[e("div",J,[e("div",M,[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=r=>k())},{default:s(()=>[t(m(V),{class:"h-4 w-4","aria-hidden":"true"}),R]),_:1})])]),e("div",U,[e("div",Z,[e("div",q,[e("table",z,[e("thead",G,[e("tr",H,[t(x,null,{default:s(()=>[d(" # ")]),_:1}),t(T,{modelName:"name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:l[1]||(l[1]=r=>N("name"))},{default:s(()=>[d(" Name ")]),_:1},8,["sortKey","sortBy"]),t(x,null,{default:s(()=>[d(" Type ")]),_:1}),t(x)])]),e("tbody",Q,[(f(!0),u(v,null,K(n.clients,(r,i)=>(f(),u("tr",{key:r.id,class:"divide-x divide-gray-200"},[t(p,{currentIndex:i,totalLength:n.clients.length,inputClass:"text-center"},{default:s(()=>[d(b(i+1),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:i,totalLength:n.clients.length,inputClass:"text-left"},{default:s(()=>[d(b(r.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:i,totalLength:n.clients.length,inputClass:"text-left"},{default:s(()=>[W]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:i,totalLength:n.clients.length,inputClass:"text-center"},{default:s(()=>[e("div",X,[t(g,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:P=>B(r)},{default:s(()=>[t(m(j),{class:"w-4 h-4"}),Y]),_:2},1032,["onClick"]),t(g,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:P=>$(r)},{default:s(()=>[t(m(A),{class:"w-4 h-4"}),ee]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.clients.length?F("",!0):(f(),u("tr",te,ne))])])])])])])]),_:1})],64))}};export{xe as default};
