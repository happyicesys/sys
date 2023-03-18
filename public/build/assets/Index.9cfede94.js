import{_ as K}from"./Authenticated.7a04ee18.js";import{_ as u}from"./Button.f622c4c1.js";import T from"./Form.fdddbb76.js";import{_ as D,r as A,T as P,a as E,b as y}from"./TableData.d9236f35.js";import{_ as H}from"./MultiSelect.2e41422a.js";import{_ as U}from"./TableHeadSort.845101a5.js";import{i as m,j as O,o as i,g as _,a as t,b as d,w as a,F as B,H as R,d as e,t as f,m as J,p as k,c as S,f as g,J as w}from"./app.146697fc.js";import{r as q}from"./PlusIcon.ee0f35bd.js";import{r as z}from"./BackspaceIcon.fa20687f.js";import{r as G}from"./PencilSquareIcon.fac99ed3.js";import{r as Q}from"./TrashIcon.ddaeb3c5.js";import"./open-closed.00420709.js";import"./use-resolve-button-type.66135bcc.js";import"./RectangleStackIcon.312a3d54.js";import"./FormInput.4d639907.js";import"./Modal.3c9c22b5.js";import"./ArrowUturnLeftIcon.db134604.js";import"./CheckCircleIcon.d84c6873.js";import"./_plugin-vue_export-helper.cdc0426e.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Setting (Banks) ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=g(" Name "),ae={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},oe={class:"flex space-x-1"},le=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),ue={class:"font-medium"},me=e("span",null,"to",-1),fe={class:"font-medium"},ge=e("span",null,"of",-1),pe={class:"font-medium"},_e=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ye={class:"bg-gray-100"},ke={class:"divide-x divide-gray-200"},we=g(" # "),Ce=g(" Name "),$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},Be=e("span",null," Edit ",-1),Se=e("span",null," Delete ",-1),Ve={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ie=[Ne],Xe={__name:"Index",props:{banks:Object},setup(n){const o=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),c=m(!1),x=m(),h=m(""),v=m([]);O(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=v.value[0]});function V(){h.value="create",x.value=null,c.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||w.Inertia.delete("/banks/"+r.id)}function I(r){h.value="update",x.value=r,c.value=!0}function b(){w.Inertia.get("/banks",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function L(){w.Inertia.get("/banks")}function M(r){o.value.sortKey=r,o.value.sortBy=!o.value.sortBy,b()}function j(){c.value=!1}return(r,s)=>(i(),_(B,null,[t(d(R),{title:"Banks"}),t(K,null,{header:a(()=>[W]),default:a(()=>{var C,$;return[e("div",X,[e("div",Y,[e("div",Z,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>V())},{default:a(()=>[t(d(q),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(D,{placeholderStr:"Name",modelValue:o.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>o.value.name=l)},{default:a(()=>[se]),_:1},8,["modelValue"])]),e("div",ae,[e("div",ne,[e("div",oe,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>b())},{default:a(()=>[t(d(A),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>L())},{default:a(()=>[t(d(z),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",ie,[e("p",de,[ce,e("span",ue,f((C=n.banks.meta.from)!=null?C:0),1),me,e("span",fe,f(($=n.banks.meta.to)!=null?$:0),1),ge,e("span",pe,f(n.banks.meta.total),1),_e]),t(H,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>o.value.numberPerPage=l),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",he,[e("div",ve,[e("table",be,[e("thead",ye,[e("tr",ke,[t(P,null,{default:a(()=>[we]),_:1}),t(U,{modelName:"name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[5]||(s[5]=l=>M("name"))},{default:a(()=>[Ce]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",$e,[(i(!0),_(B,null,J(n.banks.data,(l,p)=>(i(),_("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(y,{currentIndex:p,totalLength:n.banks.length,inputClass:"text-center"},{default:a(()=>[g(f(n.banks.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(y,{currentIndex:p,totalLength:n.banks.length,inputClass:"text-left"},{default:a(()=>[g(f(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(y,{currentIndex:p,totalLength:n.banks.length,inputClass:"text-center"},{default:a(()=>[e("div",Pe,[t(u,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>I(l)},{default:a(()=>[t(d(G),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"]),t(u,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>N(l)},{default:a(()=>[t(d(Q),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.banks.data.length?k("",!0):(i(),_("tr",Ve,Ie))])]),n.banks.data.length?(i(),S(E,{key:0,links:n.banks.links,meta:n.banks.meta},null,8,["links","meta"])):k("",!0)])])])]),c.value?(i(),S(T,{key:0,bank:x.value,type:h.value,showModal:c.value,onModalClose:j},null,8,["bank","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Xe as default};
