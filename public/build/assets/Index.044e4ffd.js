import{_ as K}from"./Authenticated.7e90e6af.js";import{_ as f}from"./Button.4631b684.js";import U from"./Form.6c1c6e63.js";import{_ as A}from"./Paginator.f614a30a.js";import{_ as E,r as T}from"./SearchInput.dcca857e.js";import{_ as B}from"./MultiSelect.0c44488a.js";import{_ as w,a as x}from"./TableData.e3cd56cb.js";import{_ as R}from"./TableHeadSort.6b73435e.js";import{g as d,h as Z,f as h,a as t,u,w as a,F as S,o as c,Z as q,b as e,d as i,t as m,k as z,l as k,c as G,O as C}from"./app.c4e47028.js";import{r as H}from"./PlusIcon.fa20410d.js";import{r as J}from"./BackspaceIcon.dae45b54.js";import{r as Q}from"./PencilSquareIcon.ac8e2982.js";import{r as W}from"./TrashIcon.eeea9cb1.js";import"./keyboard.58689cfa.js";import"./use-resolve-button-type.ef81a21b.js";import"./RectangleStackIcon.b91bd271.js";import"./FormInput.2fd92b17.js";import"./FormTextarea.c34ee5c9.js";import"./Modal.9639af57.js";import"./disposables.be045d92.js";import"./ArrowUturnLeftIcon.8d3b347e.js";import"./CheckCircleIcon.3fae9752.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dac4e319.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Category Groups) ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Categories ",-1),le={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},re={class:"flex space-x-1"},ie=e("span",null," Search ",-1),de=e("span",null," Reset ",-1),ue={class:"flex flex-col space-y-2"},ce={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),ge={class:"font-medium"},pe=e("span",null,"to",-1),fe={class:"font-medium"},xe=e("span",null,"of",-1),he={class:"font-medium"},_e=e("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},ye={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},be={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ke={class:"bg-gray-100"},Ce={class:"divide-x divide-gray-200"},$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},Ve=e("span",null," Edit ",-1),Be=e("span",null," Delete ",-1),Se={key:0},Ge=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Le=[Ge],tt={__name:"Index",props:{categories:Object,categoryGroups:Object},setup(l){const L=l,n=d({name:"",categories:[],sortKey:"",sortBy:!0,numberPerPage:100}),g=d(!1),_=d(),v=d(""),y=d([]),$=d([]);Z(()=>{$.value=L.categories.data,y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=y.value[0]});function N(){v.value="create",_.value=null,g.value=!0}function M(r){!confirm("Are you sure to delete "+r.name+"?")||C.delete("/category-groups/"+r.id)}function O(r){v.value="update",_.value=r,g.value=!0}function b(){C.get("/category-groups",{...n.value,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(){C.get("/category-groups")}function D(r){n.value.sortKey=r,n.value.sortBy=!n.value.sortBy,b()}function F(){g.value=!1}return(r,s)=>(c(),h(S,null,[t(u(q),{title:"Category Groups"}),t(K,null,{header:a(()=>[X]),default:a(()=>{var P,V;return[e("div",Y,[e("div",ee,[e("div",te,[t(f,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=o=>N())},{default:a(()=>[t(u(H),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",oe,[t(E,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":s[1]||(s[1]=o=>n.value.name=o)},{default:a(()=>[i(" Name ")]),_:1},8,["modelValue"]),e("div",null,[ae,t(B,{modelValue:n.value.categories,"onUpdate:modelValue":s[2]||(s[2]=o=>n.value.categories=o),options:$.value,valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",le,[e("div",ne,[e("div",re,[t(f,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=o=>b())},{default:a(()=>[t(u(T),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1}),t(f,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[4]||(s[4]=o=>j())},{default:a(()=>[t(u(J),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1})])]),e("div",ue,[e("p",ce,[me,e("span",ge,m((P=l.categoryGroups.meta.from)!=null?P:0),1),pe,e("span",fe,m((V=l.categoryGroups.meta.to)!=null?V:0),1),xe,e("span",he,m(l.categoryGroups.meta.total),1),_e]),t(B,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":s[5]||(s[5]=o=>n.value.numberPerPage=o),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",ve,[e("div",ye,[e("div",be,[e("table",we,[e("thead",ke,[e("tr",Ce,[t(w,null,{default:a(()=>[i(" # ")]),_:1}),t(R,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[6]||(s[6]=o=>D("name"))},{default:a(()=>[i(" Name ")]),_:1},8,["sortKey","sortBy"]),t(w,null,{default:a(()=>[i(" Desc ")]),_:1}),t(w)])]),e("tbody",$e,[(c(!0),h(S,null,z(l.categoryGroups.data,(o,p)=>(c(),h("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(x,{currentIndex:p,totalLength:l.categoryGroups.length,inputClass:"text-center"},{default:a(()=>[i(m(l.categoryGroups.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:p,totalLength:l.categoryGroups.length,inputClass:"text-left"},{default:a(()=>[i(m(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:p,totalLength:l.categoryGroups.length,inputClass:"text-left whitespace-pre-wrap"},{default:a(()=>[i(m(o.desc),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:p,totalLength:l.categoryGroups.length,inputClass:"text-center"},{default:a(()=>[e("div",Pe,[t(f,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:I=>O(o)},{default:a(()=>[t(u(Q),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"]),t(f,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:I=>M(o)},{default:a(()=>[t(u(W),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.categoryGroups.data.length?k("",!0):(c(),h("tr",Se,Le))])]),l.categoryGroups.data.length?(c(),G(A,{key:0,links:l.categoryGroups.links,meta:l.categoryGroups.meta},null,8,["links","meta"])):k("",!0)])])])]),g.value?(c(),G(U,{key:0,categoryGroup:_.value,type:v.value,showModal:g.value,onModalClose:F},null,8,["categoryGroup","type","showModal"])):k("",!0)]}),_:1})],64))}};export{tt as default};
